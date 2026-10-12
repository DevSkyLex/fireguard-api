<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Authorization\Infrastructure\Persistence\Doctrine\Record\{RoleAssignmentRecord, RoleRecord};
use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Intervention\Infrastructure\Persistence\Doctrine\Record\InterventionRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\{Connection as QueueConnection, DoctrineTransport};
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Tests\Support\Auth\InteractiveTokenFactory;
use Tests\Support\Database\RootCommitDatabaseIsolationTrait;
use User\Infrastructure\Persistence\Doctrine\Record\UserRecord;

use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * Class HttpIdempotencyEquipmentCreationApiTest
 *
 * Proves offline creation retries restore their committed result while new intentions and revoked grants remain guarded.
 *
 * @category Functional Test
 */
#[SkipDatabaseRollback]
final class HttpIdempotencyEquipmentCreationApiTest extends WebTestCase
{
  use RootCommitDatabaseIsolationTrait;

  private const string USER = 'ec000000-0000-4000-8000-000000000001';

  private const string ORGANIZATION = 'ec000000-0000-4000-8000-000000000002';

  private const string ROLE = 'ec000000-0000-4000-8000-000000000003';

  private const string CLIENT_ID = 'ec000000-0000-4000-8000-000000000004';

  private const string INTERVENTION = 'ec000000-0000-4000-8000-000000000005';

  /**
   * @return iterable<string, array{string, bool}> native offline protocols and optional lifecycle context
   */
  public static function creations(): iterable
  {
    yield 'POST' => ['POST', false];
    yield 'PUT' => ['PUT', false];
    yield 'POST intervention' => ['POST', true];
    yield 'PUT intervention' => ['PUT', true];
  }

  #[Test]
  #[DataProvider('creations')]
  public function exactRetryRestoresTheOriginalCreationAfterItsIdentityExistsAndParentChanges(string $method, bool $intervention): void
  {
    $client = self::createClient();
    $database = $this->seed($client, $intervention);
    $uri = 'PUT' === $method ? '/api/equipment/' . self::CLIENT_ID : '/api/equipment';
    $headers = ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json', 'HTTP_IDEMPOTENCY_KEY' => 'offline-equipment-create'];
    if ('PUT' === $method) {
      $headers['HTTP_IF_NONE_MATCH'] = '*';
    }
    $fields = ['organization' => '/api/organizations/' . self::ORGANIZATION, 'type' => 'fire_extinguisher', 'name' => 'Original offline equipment'];
    if ('POST' === $method) {
      $fields['clientId'] = self::CLIENT_ID;
    }
    if ($intervention) {
      $fields['intervention'] = '/api/interventions/' . self::INTERVENTION;
    }
    $content = json_encode($fields, JSON_THROW_ON_ERROR);
    $before = $database->fetchOne("SELECT COUNT(*) FROM messenger_messages WHERE queue_name = 'main_outbox'");
    self::assertIsInt($before);
    $client->request($method, $uri, server: $headers, content: $content);
    self::assertResponseStatusCodeSame(201);
    $original = $this->body($client);
    $id = $original['id'];
    self::assertIsString($id);
    $revision = $database->fetchOne('SELECT revision FROM equipment WHERE id = ?', [$id]);
    self::assertIsInt($revision);
    self::assertSame('Original offline equipment', $original['name']);
    self::assertSame(1, $database->fetchOne('SELECT COUNT(*) FROM equipment WHERE client_id = ?', [self::CLIENT_ID]));
    $outbox = $database->fetchOne("SELECT COUNT(*) FROM messenger_messages WHERE queue_name = 'main_outbox'");
    self::assertIsInt($outbox);
    self::assertGreaterThan($before, $outbox);

    $freshHeaders = $headers;
    $freshHeaders['HTTP_IDEMPOTENCY_KEY'] = 'another-offline-equipment-intention';
    $client->request($method, $uri, server: $freshHeaders, content: $content);
    self::assertResponseStatusCodeSame('PUT' === $method ? 412 : 409);
    self::assertFalse($client->getResponse()->headers->has('Idempotency-Replayed'));

    $database->executeStatement('UPDATE equipment SET name = ?, revision = revision + 1 WHERE id = ?', ['Later equipment state', $id]);
    if ($intervention) {
      $database->executeStatement('UPDATE interventions SET status = ? WHERE id = ?', ['published', self::INTERVENTION]);
    }
    $client->request($method, $uri, server: $headers, content: $content);
    self::assertResponseStatusCodeSame(201);
    self::assertResponseHeaderSame('Idempotency-Replayed', 'true');
    self::assertSame($original, $this->body($client));
    self::assertSame('Later equipment state', $database->fetchOne('SELECT name FROM equipment WHERE id = ?', [$id]));
    self::assertSame($revision + 1, $database->fetchOne('SELECT revision FROM equipment WHERE id = ?', [$id]));
    self::assertSame(1, $database->fetchOne('SELECT COUNT(*) FROM equipment WHERE client_id = ?', [self::CLIENT_ID]));
    self::assertSame($outbox, $database->fetchOne("SELECT COUNT(*) FROM messenger_messages WHERE queue_name = 'main_outbox'"));

    $database->executeStatement('UPDATE organization_roles SET permissions = ? WHERE id = ?', ['[]', self::ROLE]);
    $client->request($method, $uri, server: $headers, content: $content);
    self::assertResponseStatusCodeSame(403);
    self::assertFalse($client->getResponse()->headers->has('Idempotency-Replayed'));
    self::assertStringNotContainsString('Original offline equipment', $client->getResponse()->getContent() ?: '');
    self::assertSame($outbox, $database->fetchOne("SELECT COUNT(*) FROM messenger_messages WHERE queue_name = 'main_outbox'"));
  }

  /**
   * @return array<array-key, mixed> public response without API Platform's transient output identity
   */
  private function body(KernelBrowser $client): array
  {
    $body = json_decode($client->getResponse()->getContent() ?: '', true, flags: JSON_THROW_ON_ERROR);
    self::assertIsArray($body);
    unset($body['@id']);

    return $body;
  }

  private function seed(KernelBrowser $client, bool $intervention): Connection
  {
    $client->disableReboot();
    $auth = self::getContainer()->get('doctrine.orm.auth_entity_manager');
    $main = self::getContainer()->get('doctrine.orm.main_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $auth);
    self::assertInstanceOf(EntityManagerInterface::class, $main);
    $sender = new DoctrineTransport(new QueueConnection(['queue_name' => 'main_outbox', 'auto_setup' => false, 'use_notify' => false], $main->getConnection()), new PhpSerializer());
    self::getContainer()->set('messenger.transport.main_outbox', $sender);
    $sender->setup();
    $now = new DateTimeImmutable();
    $user = new UserRecord();
    $user->id = self::USER;
    $user->username = 'offline-equipment-creation';
    $user->email = 'offline-equipment-creation@example.test';
    $user->firstName = 'Offline';
    $user->lastName = 'Creation';
    $user->status = 'active';
    $user->emailVerified = true;
    $user->emailOwnershipVerifiedAt = $now;
    $user->createdAt = $now;
    $auth->persist($user);
    $platformRole = $auth->getRepository(RoleRecord::class)->findOneBy(['name' => 'user']);
    self::assertInstanceOf(RoleRecord::class, $platformRole);
    $platformAssignment = new RoleAssignmentRecord();
    $platformAssignment->id = 'ec000000-0000-4000-8000-000000000008';
    $platformAssignment->roleId = $platformRole->id;
    $platformAssignment->role = $platformRole;
    $platformAssignment->subjectType = 'user';
    $platformAssignment->subjectId = self::USER;
    $platformAssignment->assignedAt = $now;
    $auth->persist($platformAssignment);
    $auth->flush();
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION;
    $organization->name = 'Offline equipment creation';
    $organization->slug = 'offline-equipment-creation';
    $organization->ownerUserId = 'ec000000-0000-4000-8000-000000000099';
    $organization->createdByUserId = self::USER;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;
    $main->persist($organization);
    $role = new OrganizationRoleRecord();
    $role->id = self::ROLE;
    $role->organization = $organization;
    $role->name = 'offline-replay-role';
    $role->permissions = ['*'];
    $role->createdAt = $now;
    $main->persist($role);
    $member = new OrganizationMemberRecord();
    $member->id = 'ec000000-0000-4000-8000-000000000006';
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
    if ($intervention) {
      $parent = new InterventionRecord();
      $parent->id = self::INTERVENTION;
      $parent->organizationId = self::ORGANIZATION;
      $parent->name = 'Offline creation draft';
      $parent->status = 'draft';
      $parent->createdAt = $now;
      $parent->updatedAt = $now;
      $main->persist($parent);
    }
    $main->flush();
    $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer ' . InteractiveTokenFactory::issue(self::getContainer(), self::USER, $user->email));

    return $main->getConnection();
  }
}
