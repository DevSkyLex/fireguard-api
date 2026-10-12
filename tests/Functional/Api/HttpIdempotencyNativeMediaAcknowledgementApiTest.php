<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Authorization\Infrastructure\Persistence\Doctrine\Record\{RoleAssignmentRecord, RoleRecord};
use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Infrastructure\Persistence\Doctrine\Record\{EquipmentAttachmentRecord, EquipmentRecord};
use Intervention\Infrastructure\Persistence\Doctrine\Record\{InterventionAttachmentRecord, InterventionRecord};
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use Shared\Application\Port\Outbound\FileStoragePort;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Tests\Support\Auth\InteractiveTokenFactory;
use Tests\Support\Database\RootCommitDatabaseIsolationTrait;
use User\Infrastructure\Persistence\Doctrine\Record\UserRecord;

use function file_put_contents;
use function json_decode;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

use const JSON_THROW_ON_ERROR;

/**
 * Test HttpIdempotencyNativeMediaAcknowledgementApiTest.
 *
 * Proves legacy client identities receive an owning committed HTTP snapshot without rewriting their bytes.
 *
 * @category Functional Test
 */
#[SkipDatabaseRollback]
final class HttpIdempotencyNativeMediaAcknowledgementApiTest extends WebTestCase
{
  use RootCommitDatabaseIsolationTrait;

  private const string USER = 'ac000000-0000-4000-8000-000000000001';

  private const string ORGANIZATION = 'ac000000-0000-4000-8000-000000000002';

  private const string ROLE = 'ac000000-0000-4000-8000-000000000003';

  private const string EQUIPMENT = 'ac000000-0000-4000-8000-000000000004';

  private const string INTERVENTION = 'ac000000-0000-4000-8000-000000000005';

  private const string ATTACHMENT = 'ac000000-0000-4000-8000-000000000006';

  /**
   * @return iterable<string, array{bool}>
   */
  public static function owners(): iterable
  {
    yield 'equipment' => [false];
    yield 'intervention' => [true];
  }

  #[Test]
  #[DataProvider('owners')]
  public function aFreshKeyAcknowledgesTheStoredNativeIdentityAndCanReplay(bool $intervention): void
  {
    $client = self::createClient();
    $database = $this->seed($client, $intervention);
    $files = $this->createMock(FileStoragePort::class);
    $files->expects(self::never())->method('write');
    $files->expects(self::never())->method('delete');
    self::getContainer()->set(FileStoragePort::class, $files);
    $this->acknowledge($client, $intervention, 'native-ack-first');
    self::assertResponseStatusCodeSame(201);
    $original = $this->body($client);
    self::assertSame(self::ATTACHMENT, $original['id']);
    self::assertSame('original.pdf', $original['fileName']);
    self::assertSame('Original attachment', $original['label']);
    self::assertSame(1, $database->fetchOne("SELECT COUNT(*) FROM http_idempotency_receipts WHERE state = 'completed'"));

    $this->acknowledge($client, $intervention, 'native-ack-first');
    self::assertResponseStatusCodeSame(201);
    self::assertResponseHeaderSame('Idempotency-Replayed', 'true');
    self::assertSame($original, $this->body($client));

    $this->acknowledge($client, $intervention, 'native-ack-second');
    self::assertResponseStatusCodeSame(201);
    self::assertSame($original, $this->body($client));
    self::assertSame(2, $database->fetchOne("SELECT COUNT(*) FROM http_idempotency_receipts WHERE state = 'completed'"));
    $table = $intervention ? 'intervention_attachments' : 'equipment_attachments';
    self::assertSame(1, $database->fetchOne('SELECT COUNT(*) FROM ' . $table . ' WHERE id = ?', [self::ATTACHMENT]));
    self::assertSame('native/original.pdf', $database->fetchOne('SELECT storage_path FROM ' . $table . ' WHERE id = ?', [self::ATTACHMENT]));
    $parentTable = $intervention ? 'interventions' : 'equipment';
    $parentId = $intervention ? self::INTERVENTION : self::EQUIPMENT;
    self::assertSame(1, $database->fetchOne('SELECT revision FROM ' . $parentTable . ' WHERE id = ?', [$parentId]));

    $this->acknowledge($client, $intervention, 'native-ack-first', 'different input');
    self::assertResponseStatusCodeSame(409);
    self::assertStringContainsString('idempotency_conflict', $client->getResponse()->getContent() ?: '');
    $database->executeStatement('UPDATE organization_roles SET permissions = ? WHERE id = ?', ['[]', self::ROLE]);
    $this->acknowledge($client, $intervention, 'native-ack-first');
    self::assertResponseStatusCodeSame(403);
    self::assertFalse($client->getResponse()->headers->has('Idempotency-Replayed'));
    self::assertStringNotContainsString('original.pdf', $client->getResponse()->getContent() ?: '');
  }

  /**
   * @return array<array-key, mixed>
   */
  private function body(KernelBrowser $client): array
  {
    $body = json_decode($client->getResponse()->getContent() ?: '', true, flags: JSON_THROW_ON_ERROR);
    self::assertIsArray($body);
    unset($body['@id']);

    return $body;
  }

  private function acknowledge(KernelBrowser $client, bool $intervention, string $key, string $label = 'ignored native retry label'): void
  {
    $fields = ['clientId' => self::ATTACHMENT, 'label' => $label];
    $headers = ['CONTENT_TYPE' => 'multipart/form-data', 'HTTP_ACCEPT' => 'application/ld+json', 'HTTP_IDEMPOTENCY_KEY' => $key];
    if ($intervention) {
      $client->request('POST', '/api/interventions/' . self::INTERVENTION . '/attachments', $fields, server: $headers);

      return;
    }
    $path = tempnam(sys_get_temp_dir(), 'fg_native_ack_');
    self::assertIsString($path);
    self::assertNotFalse(file_put_contents($path, 'native retry bytes are not a new upload'));

    try {
      $client->request('POST', '/api/media', ['equipment' => '/api/equipment/' . self::EQUIPMENT] + $fields, ['file' => new UploadedFile($path, 'retry.pdf', 'application/pdf', test: true)], $headers);
    } finally {
      unlink($path);
    }
  }

  private function seed(KernelBrowser $client, bool $intervention): Connection
  {
    $client->disableReboot();
    $auth = self::getContainer()->get('doctrine.orm.auth_entity_manager');
    $main = self::getContainer()->get('doctrine.orm.main_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $auth);
    self::assertInstanceOf(EntityManagerInterface::class, $main);
    $now = new DateTimeImmutable();
    $user = new UserRecord();
    $user->id = self::USER;
    $user->username = 'native-media-http-test';
    $user->email = 'native-media-http@example.test';
    $user->firstName = 'HTTP';
    $user->lastName = 'Replay';
    $user->status = 'active';
    $user->emailVerified = true;
    $user->emailOwnershipVerifiedAt = $now;
    $user->createdAt = $now;
    $auth->persist($user);
    $platformRole = $auth->getRepository(RoleRecord::class)->findOneBy(['name' => 'user']);
    self::assertInstanceOf(RoleRecord::class, $platformRole);
    $platformAssignment = new RoleAssignmentRecord();
    $platformAssignment->id = 'ac000000-0000-4000-8000-000000000008';
    $platformAssignment->roleId = $platformRole->id;
    $platformAssignment->role = $platformRole;
    $platformAssignment->subjectType = 'user';
    $platformAssignment->subjectId = self::USER;
    $platformAssignment->assignedAt = $now;
    $auth->persist($platformAssignment);
    $auth->flush();
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION;
    $organization->name = 'HTTP idempotency';
    $organization->slug = 'native-media-http-test';
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
    $member->id = 'ac000000-0000-4000-8000-000000000007';
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
    $equipment->recordStatus = 'published';
    $equipment->createdAt = $now;
    $equipment->updatedAt = $now;
    $main->persist($equipment);
    if ($intervention) {
      $parent = new InterventionRecord();
      $parent->id = self::INTERVENTION;
      $parent->organizationId = self::ORGANIZATION;
      $parent->name = 'Published native replay';
      $parent->status = 'published';
      $parent->createdAt = $now;
      $parent->updatedAt = $now;
      $main->persist($parent);
      $attachment = new InterventionAttachmentRecord();
      $attachment->intervention = $parent;
    } else {
      $attachment = new EquipmentAttachmentRecord();
      $attachment->equipment = $equipment;
    }
    $attachment->id = self::ATTACHMENT;
    $attachment->fileName = 'original.pdf';
    $attachment->storagePath = 'native/original.pdf';
    $attachment->mimeType = 'application/pdf';
    $attachment->size = 5;
    $attachment->label = 'Original attachment';
    $attachment->uploadedAt = $now;
    $main->persist($attachment);
    $main->flush();
    $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer ' . InteractiveTokenFactory::issue(self::getContainer(), self::USER, $user->email));

    return $main->getConnection();
  }
}
