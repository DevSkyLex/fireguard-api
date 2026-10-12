<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Import\Application\Port\Outbound\ImportJobQueuePort;
use Import\Application\Port\Outbound\Internal\{ImportJobRepositoryPort, ImportKindRegistryPort};
use Import\Application\UseCase\Command\CreateImportJob\CreateImportJobHandler;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\Test;
use Shared\Application\Contract\Idempotency\IdempotencyDeniedException;
use Shared\Application\Port\Outbound\{FileStoragePort, LoggerPort, UuidGeneratorPort};
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyExecutionPort;
use Shared\Infrastructure\Messaging\Outbox\RootTransactionManagerAdapter;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\{Connection as QueueConnection, DoctrineTransport};
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
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
 * Test HttpIdempotencyImportCreationApiTest
 *
 * Proves CSV storage, queued processing and the HTTP result share the final main commit using an actual PostgreSQL sender.
 *
 * @category Functional Test
 */
#[SkipDatabaseRollback]
final class HttpIdempotencyImportCreationApiTest extends WebTestCase
{
  use RootCommitDatabaseIsolationTrait;

  private const string USER = 'ad000000-0000-4000-8000-000000000001';

  private const string ORGANIZATION = 'ad000000-0000-4000-8000-000000000002';

  private const string ROLE = 'ad000000-0000-4000-8000-000000000003';

  private const string CSV = "type\nfire_extinguisher\n";

  /**
   * @var array<string, string>
   */
  private array $blobs = [];

  private int $writes = 0;

  private int $deletes = 0;

  #[Test]
  public function creationReplaysOneJobFileAndQueuedCommand(): void
  {
    [$client, $database] = $this->seed();
    $this->upload($client, self::CSV);
    self::assertResponseStatusCodeSame(202);
    $first = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
    self::assertIsArray($first);
    self::assertSame(1, $this->writes);
    self::assertCount(1, $this->blobs);
    self::assertSame(1, self::rowCount($database, 'SELECT COUNT(*) FROM import_jobs'));
    self::assertSame(1, self::rowCount($database, 'SELECT COUNT(*) FROM messenger_messages'));
    self::assertSame(1, self::rowCount($database, "SELECT COUNT(*) FROM http_idempotency_receipts WHERE state = 'completed'"));
    $file = $this->blobs;

    $this->upload($client, self::CSV);
    self::assertResponseStatusCodeSame(202);
    self::assertResponseHeaderSame('Idempotency-Replayed', 'true');
    $replay = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
    self::assertIsArray($replay);
    unset($first['@id'], $replay['@id']);
    self::assertSame($first, $replay);
    self::assertSame(1, $this->writes);
    self::assertSame($file, $this->blobs);
    self::assertSame(1, self::rowCount($database, 'SELECT COUNT(*) FROM import_jobs'));
    self::assertSame(1, self::rowCount($database, 'SELECT COUNT(*) FROM messenger_messages'));

    $this->upload($client, "type\nfire_hose_reel\n");
    self::assertResponseStatusCodeSame(409);
    self::assertStringContainsString('idempotency_conflict', (string) $client->getResponse()->getContent());
    self::assertSame(1, $this->writes);
    self::assertSame($file, $this->blobs);
    self::assertSame(0, $this->deletes);
  }

  #[Test]
  public function captureFailureRollsBackTheJobQueueAndReceiptThenCompensatesBytes(): void
  {
    [$client, $database, $files] = $this->seed();
    $container = static::getContainer();
    $capture = $this->createMock(HttpIdempotencyExecutionPort::class);
    $capture->expects(self::once())->method('captureResult')->willReturnCallback(static function () use ($database): never {
      Assert::assertTrue($database->isTransactionActive());
      Assert::assertSame(1, self::rowCount($database, 'SELECT COUNT(*) FROM import_jobs'));
      Assert::assertSame(1, self::rowCount($database, 'SELECT COUNT(*) FROM messenger_messages'));

      throw new IdempotencyDeniedException('result_unavailable');
    });
    $repository = $container->get(ImportJobRepositoryPort::class);
    self::assertInstanceOf(ImportJobRepositoryPort::class, $repository);
    $queue = $container->get(ImportJobQueuePort::class);
    self::assertInstanceOf(ImportJobQueuePort::class, $queue);
    $authorization = $container->get(OrganizationAuthorizationPort::class);
    self::assertInstanceOf(OrganizationAuthorizationPort::class, $authorization);
    $uuidFactory = $container->get(UuidGeneratorPort::class);
    self::assertInstanceOf(UuidGeneratorPort::class, $uuidFactory);
    $transactionManager = $container->get(RootTransactionManagerAdapter::class);
    self::assertInstanceOf(RootTransactionManagerAdapter::class, $transactionManager);
    $kinds = $container->get(ImportKindRegistryPort::class);
    self::assertInstanceOf(ImportKindRegistryPort::class, $kinds);
    $logger = $container->get(LoggerPort::class);
    self::assertInstanceOf(LoggerPort::class, $logger);
    $container->set(CreateImportJobHandler::class, new CreateImportJobHandler(
      repository: $repository,
      fileStorage: $files,
      queue: $queue,
      authorization: $authorization,
      uuidFactory: $uuidFactory,
      transactionManager: $transactionManager,
      kinds: $kinds,
      idempotency: $capture,
      logger: $logger,
    ));
    $this->upload($client, self::CSV);
    self::assertResponseStatusCodeSame(409);
    self::assertStringContainsString('idempotency_result_unavailable', (string) $client->getResponse()->getContent());
    self::assertSame(1, $this->writes);
    self::assertSame(1, $this->deletes);
    self::assertSame([], $this->blobs);
    self::assertSame(0, self::rowCount($database, 'SELECT COUNT(*) FROM import_jobs'));
    self::assertSame(0, self::rowCount($database, 'SELECT COUNT(*) FROM messenger_messages'));
    self::assertSame(0, self::rowCount($database, "SELECT COUNT(*) FROM http_idempotency_receipts WHERE state = 'completed' OR result_payload IS NOT NULL"));
    self::assertSame('pending', $database->fetchOne('SELECT state FROM http_idempotency_receipts'));
    self::assertFalse($client->getResponse()->headers->has('Idempotency-Replayed'));
  }

  /**
   * @return array{KernelBrowser, Connection, FileStoragePort}
   */
  private function seed(): array
  {
    $client = static::createClient();
    $client->disableReboot();
    $container = static::getContainer();
    $auth = $container->get('doctrine.orm.auth_entity_manager');
    $main = $container->get('doctrine.orm.main_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $auth);
    self::assertInstanceOf(EntityManagerInterface::class, $main);
    $database = $main->getConnection();
    $files = $this->createStub(FileStoragePort::class);
    $files->method('write')->willReturnCallback(function (string $path, string $contents) use ($database): void {
      self::assertTrue($database->isTransactionActive());
      self::assertSame('writing', $database->fetchOne('SELECT state FROM http_idempotency_receipts'));
      ++$this->writes;
      $this->blobs[$path] = $contents;
    });
    $files->method('delete')->willReturnCallback(function (string $path) use ($database): void {
      self::assertFalse($database->isTransactionActive());
      ++$this->deletes;
      unset($this->blobs[$path]);
    });
    $container->set(FileStoragePort::class, $files);
    $sender = new DoctrineTransport(new QueueConnection(['queue_name' => 'main_outbox', 'auto_setup' => false, 'use_notify' => false], $database), new PhpSerializer());
    $container->set('messenger.transport.main_outbox', $sender);
    $sender->setup();
    $now = new DateTimeImmutable();
    $user = new UserRecord();
    $user->id = self::USER;
    $user->username = 'import-http-idempotency';
    $user->email = 'import-http-idempotency@example.test';
    $user->firstName = 'Import';
    $user->lastName = 'Replay';
    $user->status = 'active';
    $user->emailVerified = true;
    $user->emailOwnershipVerifiedAt = $now;
    $user->createdAt = $now;
    $auth->persist($user);
    $auth->flush();
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION;
    $organization->name = 'Import root transaction';
    $organization->slug = 'import-root-transaction';
    $organization->ownerUserId = 'ad000000-0000-4000-8000-000000000099';
    $organization->createdByUserId = self::USER;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;
    $main->persist($organization);
    $role = new OrganizationRoleRecord();
    $role->id = self::ROLE;
    $role->organization = $organization;
    $role->name = 'import-http-role';
    $role->permissions = ['*'];
    $role->createdAt = $now;
    $main->persist($role);
    $member = new OrganizationMemberRecord();
    $member->id = 'ad000000-0000-4000-8000-000000000005';
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
    $main->flush();
    $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer ' . InteractiveTokenFactory::issue($container, self::USER, $user->email));

    return [$client, $database, $files];
  }

  private static function rowCount(Connection $database, string $sql): int
  {
    $count = $database->fetchOne($sql);
    self::assertIsInt($count);

    return $count;
  }

  private function upload(KernelBrowser $client, string $contents): void
  {
    $path = tempnam(sys_get_temp_dir(), 'fg_import_http_');
    self::assertIsString($path);
    self::assertNotFalse(file_put_contents($path, $contents));

    try {
      $client->request('POST', '/api/imports', parameters: ['organization' => '/api/organizations/' . self::ORGANIZATION, 'kind' => 'equipment'], files: ['file' => new UploadedFile($path, 'equipment.csv', 'text/csv', test: true)], server: [
        'CONTENT_TYPE' => 'multipart/form-data',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_IDEMPOTENCY_KEY' => 'import-offline-upload',
      ]);
    } finally {
      unlink($path);
    }
  }
}
