<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Tests\Support\Auth\InteractiveTokenFactory;
use Tests\Support\Database\RootCommitDatabaseIsolationTrait;
use User\Infrastructure\Persistence\Doctrine\Record\UserRecord;

use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * Class HttpIdempotencyEquipmentApiTest
 *
 * Verifies the actual HTTP activation, original preconditions and fresh permission gates across real owning commits.
 *
 * @category Functional Test
 */
#[SkipDatabaseRollback]
final class HttpIdempotencyEquipmentApiTest extends WebTestCase
{
  use RootCommitDatabaseIsolationTrait;

  private const string USER = 'ed000000-0000-4000-8000-000000000001';

  private const string ORGANIZATION = 'ed000000-0000-4000-8000-000000000002';

  private const string ROLE = 'ed000000-0000-4000-8000-000000000003';

  private const string EQUIPMENT = 'ed000000-0000-4000-8000-000000000004';

  #[Test]
  public function patchReplayUsesTheOriginalIfMatchAndResponseButRechecksCurrentPermissions(): void
  {
    $client = self::createClient();
    $this->seed($client);
    $headers = ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json', 'HTTP_IF_MATCH' => '"revision-1"', 'HTTP_IDEMPOTENCY_KEY' => 'offline-patch'];
    $client->request('PATCH', '/api/equipment/' . self::EQUIPMENT, server: $headers, content: '{"name":"committed-original"}');
    self::assertResponseStatusCodeSame(200);
    $original = $client->getResponse()->getContent();
    self::assertIsString($original);
    self::assertSame(2, $this->main()->fetchOne('SELECT revision FROM equipment WHERE id = ?', [self::EQUIPMENT]));
    self::assertSame(1, $this->main()->fetchOne("SELECT COUNT(*) FROM http_idempotency_receipts WHERE state = 'completed'"));
    $this->main()->executeStatement('UPDATE equipment SET name = ?, revision = revision + 1 WHERE id = ?', ['later-state', self::EQUIPMENT]);

    $client->request('PATCH', '/api/equipment/' . self::EQUIPMENT, server: $headers, content: '{"name":"committed-original"}');
    self::assertResponseStatusCodeSame(200);
    self::assertResponseHeaderSame('Idempotency-Replayed', 'true');
    $originalFields = json_decode($original, true, flags: JSON_THROW_ON_ERROR);
    $replayedFields = json_decode($client->getResponse()->getContent() ?: '', true, flags: JSON_THROW_ON_ERROR);
    self::assertIsArray($originalFields);
    self::assertIsArray($replayedFields);
    // API Platform creates a transient skolem IRI for this output DTO on every serialization.
    unset($originalFields['@id'], $replayedFields['@id']);
    self::assertSame($originalFields, $replayedFields);
    self::assertSame(3, $this->main()->fetchOne('SELECT revision FROM equipment WHERE id = ?', [self::EQUIPMENT]));
    self::assertSame('later-state', $this->main()->fetchOne('SELECT name FROM equipment WHERE id = ?', [self::EQUIPMENT]));

    // Write access remains valid; the saved output also requires the read permission captured before commit.
    $this->main()->executeStatement('UPDATE organization_roles SET permissions = ? WHERE id = ?', ['["organization.equipment.write"]', self::ROLE]);
    $client->request('PATCH', '/api/equipment/' . self::EQUIPMENT, server: $headers, content: '{"name":"committed-original"}');
    self::assertResponseStatusCodeSame(403);
    self::assertResponseHasHeader('Content-Type');
    self::assertFalse($client->getResponse()->headers->has('Idempotency-Replayed'));
    self::assertStringNotContainsString('committed-original', $client->getResponse()->getContent() ?: '');
    self::assertSame(3, $this->main()->fetchOne('SELECT revision FROM equipment WHERE id = ?', [self::EQUIPMENT]));
  }

  #[Test]
  public function aDraftDeleteReplaysAfterTheRowIsGoneUsingSignedOriginalScope(): void
  {
    $client = self::createClient();
    $this->seed($client, draft: true);
    $headers = ['HTTP_IF_MATCH' => '"revision-1"', 'HTTP_IDEMPOTENCY_KEY' => 'offline-delete'];
    $client->request('DELETE', '/api/equipment/' . self::EQUIPMENT, server: $headers);
    self::assertResponseStatusCodeSame(204);
    self::assertSame(0, $this->main()->fetchOne('SELECT COUNT(*) FROM equipment WHERE id = ?', [self::EQUIPMENT]));
    $client->request('DELETE', '/api/equipment/' . self::EQUIPMENT, server: $headers);
    self::assertResponseStatusCodeSame(204);
    self::assertResponseHeaderSame('Idempotency-Replayed', 'true');
    self::assertSame(1, $this->main()->fetchOne("SELECT COUNT(*) FROM http_idempotency_receipts WHERE state = 'completed'"));
    $this->main()->executeStatement('UPDATE organization_members SET is_active = FALSE WHERE user_id = ? AND organization_id = ?', [self::USER, self::ORGANIZATION]);
    $client->request('DELETE', '/api/equipment/' . self::EQUIPMENT, server: $headers);
    self::assertResponseStatusCodeSame(403);
    self::assertFalse($client->getResponse()->headers->has('Idempotency-Replayed'));
  }

  #[Test]
  public function aDifferentInputWithTheSameLogicalKeyCannotMutateAgain(): void
  {
    $client = self::createClient();
    $this->seed($client);
    $headers = ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json', 'HTTP_IF_MATCH' => '"revision-1"', 'HTTP_IDEMPOTENCY_KEY' => 'same-key'];
    $client->request('PATCH', '/api/equipment/' . self::EQUIPMENT, server: $headers, content: '{"name":"one"}');
    self::assertResponseStatusCodeSame(200);
    $client->request('PATCH', '/api/equipment/' . self::EQUIPMENT, server: $headers, content: '{"name":"two"}');
    self::assertResponseStatusCodeSame(409);
    self::assertStringContainsString('idempotency_conflict', $client->getResponse()->getContent() ?: '');
    self::assertSame('one', $this->main()->fetchOne('SELECT name FROM equipment WHERE id = ?', [self::EQUIPMENT]));
    self::assertSame(2, $this->main()->fetchOne('SELECT revision FROM equipment WHERE id = ?', [self::EQUIPMENT]));
  }

  private function main(): Connection
  {
    $connection = self::getContainer()->get('doctrine.dbal.main_connection');
    self::assertInstanceOf(Connection::class, $connection);

    return $connection;
  }

  private function seed(KernelBrowser $client, bool $draft = false): void
  {
    $client->disableReboot();
    $auth = self::getContainer()->get('doctrine.orm.auth_entity_manager');
    $main = self::getContainer()->get('doctrine.orm.main_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $auth);
    self::assertInstanceOf(EntityManagerInterface::class, $main);
    $now = new DateTimeImmutable();
    $user = new UserRecord();
    $user->id = self::USER;
    $user->username = 'idempotency-http-test';
    $user->email = 'idempotency-http@example.test';
    $user->firstName = 'HTTP';
    $user->lastName = 'Replay';
    $user->status = 'active';
    $user->emailVerified = true;
    $user->emailOwnershipVerifiedAt = $now;
    $user->createdAt = $now;
    $auth->persist($user);
    $auth->flush();
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION;
    $organization->name = 'HTTP idempotency';
    $organization->slug = 'http-idempotency-test';
    $organization->ownerUserId = 'ed000000-0000-4000-8000-000000000099';
    $organization->createdByUserId = self::USER;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;
    $main->persist($organization);
    $role = new OrganizationRoleRecord();
    $role->id = self::ROLE;
    $role->organization = $organization;
    $role->name = 'http-replay-role';
    $role->permissions = ['*'];
    $role->createdAt = $now;
    $main->persist($role);
    $member = new OrganizationMemberRecord();
    $member->id = 'ed000000-0000-4000-8000-000000000005';
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
    $equipment = new EquipmentRecord();
    $equipment->id = self::EQUIPMENT;
    $equipment->organizationId = self::ORGANIZATION;
    $equipment->type = 'fire_extinguisher';
    $equipment->status = 'in_stock';
    $equipment->recordStatus = $draft ? 'draft' : 'published';
    $equipment->createdAt = $now;
    $equipment->updatedAt = $now;
    $main->persist($equipment);
    $main->flush();
    $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer ' . InteractiveTokenFactory::issue(self::getContainer(), self::USER, $user->email));
  }
}
