<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Inspection\Infrastructure\Persistence\Doctrine\Record\InspectionRecord;
use Intervention\Infrastructure\Persistence\Doctrine\Record\InterventionRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Tests\Support\Auth\InteractiveTokenFactory;
use Tests\Support\Database\RootCommitDatabaseIsolationTrait;
use User\Infrastructure\Persistence\Doctrine\Record\UserRecord;

use function count;
use function is_array;
use function is_string;
use function json_decode;
use function str_starts_with;

use const JSON_THROW_ON_ERROR;

/**
 * Class HttpIdempotencyInspectionMutationApiTest
 *
 * Exercises original canonical mutation results after parent publication while keeping current grants and participation gates.
 *
 * @category Functional Test
 */
#[SkipDatabaseRollback]
final class HttpIdempotencyInspectionMutationApiTest extends WebTestCase
{
  use RootCommitDatabaseIsolationTrait;

  private const string USER = 'fa120000-0000-4000-8000-000000000001';

  private const string ORGANIZATION = 'fa120000-0000-4000-8000-000000000002';

  private const string ROLE = 'fa120000-0000-4000-8000-000000000003';

  private const string MEMBER = 'fa120000-0000-4000-8000-000000000004';

  private const string INTERVENTION = 'fa120000-0000-4000-8000-000000000005';

  private const string INSPECTION = 'fa120000-0000-4000-8000-000000000006';

  /**
   * @return iterable<string, array{string, int}> canonical mutation contracts
   */
  public static function mutations(): iterable
  {
    yield 'patch' => ['PATCH', 200];
    yield 'delete' => ['DELETE', 204];
  }

  #[DataProvider('mutations')]
  public function testCommittedMutationReplaysAfterParentPublicationAndStillRequiresCurrentGrants(string $method, int $status): void
  {
    $client = self::createClient();
    $this->seed($client);
    $before = $this->outboxCount();
    $this->mutate($client, $method, 'original-mutation');
    self::assertResponseStatusCodeSame($status);
    $original = $this->payload($client, $method);
    self::assertSame(1, $this->completedReceiptCount());
    self::assertSame('PATCH' === $method ? 2 : false, $this->main()->fetchOne('SELECT revision FROM inspections WHERE id = ?', [self::INSPECTION]));
    $parentRevision = $this->main()->fetchOne('SELECT revision FROM interventions WHERE id = ?', [self::INTERVENTION]);
    self::assertIsInt($parentRevision);
    self::assertSame(2, $parentRevision);
    // Scratchpad writes are deliberately unaudited; a replay cannot introduce a new outbox intent.
    self::assertSame($before, $this->outboxCount());
    $this->main()->executeStatement("UPDATE interventions SET status = 'published' WHERE id = ?", [self::INTERVENTION]);
    if ('PATCH' === $method) {
      $this->main()->executeStatement('UPDATE inspections SET notes = ?, revision = revision + 1 WHERE id = ?', ['later-inspection-state', self::INSPECTION]);
    }

    $this->mutate($client, $method, 'original-mutation');
    self::assertResponseStatusCodeSame($status);
    self::assertResponseHeaderSame('Idempotency-Replayed', 'true');
    self::assertSame($original, $this->payload($client, $method));
    self::assertSame('PATCH' === $method ? 3 : false, $this->main()->fetchOne('SELECT revision FROM inspections WHERE id = ?', [self::INSPECTION]));
    self::assertSame($parentRevision, $this->main()->fetchOne('SELECT revision FROM interventions WHERE id = ?', [self::INTERVENTION]));
    self::assertSame($before, $this->outboxCount());
    self::assertSame(1, $this->completedReceiptCount());

    $this->main()->executeStatement('UPDATE organization_roles SET permissions = ? WHERE id = ?', ['["organization.inspection.read","organization.inspection.write","organization.interventions.read"]', self::ROLE]);
    $this->mutate($client, $method, 'original-mutation');
    self::assertResponseStatusCodeSame(403);
    self::assertFalse($client->getResponse()->headers->has('Idempotency-Replayed'));
    self::assertStringNotContainsString('committed-original', $client->getResponse()->getContent() ?: '');
    self::assertSame($parentRevision, $this->main()->fetchOne('SELECT revision FROM interventions WHERE id = ?', [self::INTERVENTION]));
    self::assertSame($before, $this->outboxCount());
    self::assertSame(1, $this->completedReceiptCount());
  }

  #[DataProvider('mutations')]
  public function testReplayStillRequiresCurrentInterventionParticipation(string $method, int $status): void
  {
    $client = self::createClient();
    $this->seed($client);
    $before = $this->outboxCount();
    $this->mutate($client, $method, 'participant-mutation');
    self::assertResponseStatusCodeSame($status);
    $this->main()->executeStatement("UPDATE interventions SET status = 'submitted', participants = '[]' WHERE id = ?", [self::INTERVENTION]);

    $this->mutate($client, $method, 'participant-mutation');
    self::assertResponseStatusCodeSame(403);
    self::assertFalse($client->getResponse()->headers->has('Idempotency-Replayed'));
    self::assertStringNotContainsString('committed-original', $client->getResponse()->getContent() ?: '');
    self::assertSame($before, $this->outboxCount());
    self::assertSame(1, $this->completedReceiptCount());
  }

  public function testANewMutationStillEnforcesTheParentLifecycleAndOriginalEtagsRemainBoundToTheirIntent(): void
  {
    $client = self::createClient();
    $this->seed($client);
    $before = $this->outboxCount();
    $this->mutate($client, 'PATCH', 'original-mutation');
    self::assertResponseStatusCodeSame(200);
    $this->main()->executeStatement("UPDATE interventions SET status = 'published' WHERE id = ?", [self::INTERVENTION]);

    $this->mutate($client, 'PATCH', 'original-mutation', '"revision-2"');
    self::assertResponseStatusCodeSame(409);
    self::assertStringContainsString('idempotency_conflict', $client->getResponse()->getContent() ?: '');
    $this->mutate($client, 'PATCH', 'new-mutation', '"revision-2"');
    self::assertResponseStatusCodeSame(403);
    self::assertFalse($client->getResponse()->headers->has('Idempotency-Replayed'));

    // The unkeyed compatibility route reaches the original mutation precondition and keeps its 409 contract.
    $main = self::getContainer()->get('doctrine.orm.main_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $main);
    $main->clear();
    $client->request('PATCH', '/api/inspections/' . self::INSPECTION, server: ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json', 'HTTP_IF_MATCH' => '"revision-2"'], content: '{"notes":"new-mutation"}');
    self::assertResponseStatusCodeSame(409);
    self::assertStringContainsString('Intervention resources are immutable', $client->getResponse()->getContent() ?: '');
    self::assertSame('committed-original', $this->main()->fetchOne('SELECT notes FROM inspections WHERE id = ?', [self::INSPECTION]));
    self::assertSame(2, $this->main()->fetchOne('SELECT revision FROM inspections WHERE id = ?', [self::INSPECTION]));
    self::assertSame($before, $this->outboxCount());
    self::assertSame(1, $this->completedReceiptCount());
  }

  private function mutate(KernelBrowser $client, string $method, string $key, string $etag = '"revision-1"'): void
  {
    $client->request($method, '/api/inspections/' . self::INSPECTION, server: ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json', 'HTTP_IF_MATCH' => $etag, 'HTTP_IDEMPOTENCY_KEY' => $key], content: 'PATCH' === $method ? '{"notes":"committed-original"}' : null);
  }

  /**
   * @return array<array-key, mixed>|string original result without API Platform's transient output skolem IRIs
   */
  private function payload(KernelBrowser $client, string $method): array|string
  {
    $content = $client->getResponse()->getContent();
    self::assertIsString($content);
    if ('DELETE' === $method) {
      return $content;
    }
    $payload = json_decode($content, true, flags: JSON_THROW_ON_ERROR);
    self::assertIsArray($payload);

    return $this->withoutGeneratedIris($payload);
  }

  /**
   * @param array<array-key, mixed> $body serialized result
   *
   * @return array<array-key, mixed> exact business result excluding only ephemeral blank-node identifiers
   */
  private function withoutGeneratedIris(array $body): array
  {
    foreach ($body as $key => $value) {
      if ('@id' === $key && is_string($value) && str_starts_with($value, '/api/.well-known/genid/')) {
        unset($body[$key]);
      } elseif (is_array($value)) {
        $body[$key] = $this->withoutGeneratedIris($value);
      }
    }

    return $body;
  }

  private function main(): Connection
  {
    $connection = self::getContainer()->get('doctrine.dbal.main_connection');
    self::assertInstanceOf(Connection::class, $connection);

    return $connection;
  }

  private function outboxCount(): int
  {
    $transport = self::getContainer()->get('messenger.transport.main_outbox');
    self::assertInstanceOf(InMemoryTransport::class, $transport);

    return count($transport->getSent());
  }

  private function completedReceiptCount(): int
  {
    $count = $this->main()->fetchOne("SELECT COUNT(*) FROM http_idempotency_receipts WHERE state = 'completed'");
    self::assertIsInt($count);

    return $count;
  }

  private function seed(KernelBrowser $client): void
  {
    $client->disableReboot();
    $auth = self::getContainer()->get('doctrine.orm.auth_entity_manager');
    $main = self::getContainer()->get('doctrine.orm.main_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $auth);
    self::assertInstanceOf(EntityManagerInterface::class, $main);
    $now = new DateTimeImmutable();
    $user = new UserRecord();
    $user->id = self::USER;
    $user->username = 'inspection-replay-test';
    $user->email = 'inspection-replay@example.test';
    $user->firstName = 'Inspection';
    $user->lastName = 'Replay';
    $user->status = 'active';
    $user->emailVerified = true;
    $user->emailOwnershipVerifiedAt = $now;
    $user->createdAt = $now;
    $auth->persist($user);
    $auth->flush();
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION;
    $organization->name = 'Inspection replay';
    $organization->slug = 'inspection-replay-test';
    $organization->ownerUserId = 'fa120000-0000-4000-8000-000000000099';
    $organization->createdByUserId = self::USER;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;
    $main->persist($organization);
    $role = new OrganizationRoleRecord();
    $role->id = self::ROLE;
    $role->organization = $organization;
    $role->name = 'inspection-replay-role';
    $role->permissions = ['organization.inspection.read', 'organization.inspection.write', 'organization.interventions.plan', 'organization.interventions.execute', 'organization.interventions.read'];
    $role->createdAt = $now;
    $main->persist($role);
    $member = new OrganizationMemberRecord();
    $member->id = self::MEMBER;
    $member->organization = $organization;
    $member->userId = self::USER;
    $member->isActive = true;
    $member->joinedAt = $now;
    $main->persist($member);
    $assignment = new OrganizationMemberRoleRecord();
    $assignment->member = $member;
    $assignment->role = $role;
    $assignment->assignedAt = $now;
    $main->persist($assignment);
    $intervention = new InterventionRecord();
    $intervention->id = self::INTERVENTION;
    $intervention->organizationId = self::ORGANIZATION;
    $intervention->name = 'Inspection replay parent';
    $intervention->status = 'planned';
    $intervention->participants = [self::MEMBER];
    $intervention->createdAt = $now;
    $intervention->updatedAt = $now;
    $main->persist($intervention);
    $inspection = new InspectionRecord();
    $inspection->id = self::INSPECTION;
    $inspection->organizationId = self::ORGANIZATION;
    $inspection->equipmentId = 'fa120000-0000-4000-8000-000000000007';
    $inspection->interventionId = self::INTERVENTION;
    $inspection->recordStatus = 'draft';
    $inspection->status = 'draft';
    $inspection->result = 'pass';
    $inspection->inspectorType = 'user';
    $inspection->inspectorName = 'Replay Inspector';
    $inspection->performedAt = $now;
    $inspection->createdAt = $now;
    $inspection->updatedAt = $now;
    $main->persist($inspection);
    $main->flush();
    $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer ' . InteractiveTokenFactory::issue(self::getContainer(), self::USER, $user->email));
  }
}
