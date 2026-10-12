<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Authorization\Infrastructure\Persistence\Doctrine\Record\{RoleAssignmentRecord, RoleRecord};
use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\Test;
use Shared\Application\Port\Outbound\{FileStoragePort, ImageInputValidationPort};
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Tests\Support\Auth\InteractiveTokenFactory;
use Tests\Support\Database\RootCommitDatabaseIsolationTrait;
use User\Application\Contract\User\AvatarVariants;
use User\Application\Port\Outbound\AvatarStoragePort;
use User\Infrastructure\Image\AvatarResizer;
use User\Infrastructure\Persistence\Doctrine\Record\UserRecord;

use function count;
use function file_put_contents;
use function imagecolorallocate;
use function imagecreatetruecolor;
use function imagedestroy;
use function imagefill;
use function imagepng;
use function json_decode;
use function ob_get_clean;
use function ob_start;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

use const JSON_THROW_ON_ERROR;

/**
 * Test HttpIdempotencyAvatarApiTest
 *
 * Exercises real authenticated multipart input, root auth commits and image encoding with an observable in-memory byte sink.
 *
 * @category Functional Test
 */
#[SkipDatabaseRollback]
final class HttpIdempotencyAvatarApiTest extends WebTestCase
{
  use RootCommitDatabaseIsolationTrait;

  private const string USER = 'ae000000-0000-4000-8000-000000000001';

  private const string ASSIGNMENT = 'ae000000-0000-4000-8000-000000000002';

  #[Test]
  public function multipartRetryReplaysTheOriginalProfileAndNeverRewritesVariants(): void
  {
    $client = static::createClient();
    $client->disableReboot();
    $auth = static::getContainer()->get('doctrine.orm.auth_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $auth);
    $database = $auth->getConnection();
    $this->seed($client, $auth);
    $storage = new class ($database) implements FileStoragePort {
      /**
       * @var array<string, string>
       */
      public array $blobs = [];

      public int $writes = 0;

      public int $deletes = 0;

      public function __construct(private Connection $connection)
      {
      }

      public function write(string $path, string $contents): void
      {
        Assert::assertTrue($this->connection->isTransactionActive());
        Assert::assertSame('writing', $this->connection->fetchOne('SELECT state FROM http_idempotency_receipts'));
        ++$this->writes;
        $this->blobs[$path] = $contents;
      }

      public function read(string $path): string
      {
        return $this->blobs[$path];
      }

      public function delete(string $path): void
      {
        ++$this->deletes;
        unset($this->blobs[$path]);
      }

      public function exists(string $path): bool
      {
        return isset($this->blobs[$path]);
      }
    };
    $validation = static::getContainer()->get(ImageInputValidationPort::class);
    self::assertInstanceOf(ImageInputValidationPort::class, $validation);
    static::getContainer()->set(AvatarStoragePort::class, new AvatarResizer($storage, $validation));
    $bytes = $this->png(255, 0, 0);
    $this->upload($client, $bytes);
    self::assertResponseStatusCodeSame(200);
    $first = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
    self::assertIsArray($first);
    $url = $first['avatarUrl'];
    self::assertIsString($url);
    self::assertStringContainsString('/api/users/' . self::USER . '/avatar/', $url);
    $pointer = $database->fetchOne('SELECT avatar_storage_key FROM users WHERE id = ?', [self::USER]);
    self::assertIsString($pointer);
    self::assertSame(count(AvatarVariants::SIZES), $storage->writes);
    $written = $storage->blobs;
    $outbox = $this->messageCount($database);

    $this->upload($client, $bytes);
    self::assertResponseStatusCodeSame(200);
    self::assertResponseHeaderSame('Idempotency-Replayed', 'true');
    $replayed = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
    self::assertIsArray($replayed);
    unset($first['@id'], $replayed['@id']);
    self::assertSame($first, $replayed);
    self::assertSame($pointer, $database->fetchOne('SELECT avatar_storage_key FROM users WHERE id = ?', [self::USER]));
    self::assertSame(count(AvatarVariants::SIZES), $storage->writes);
    self::assertSame($written, $storage->blobs);
    self::assertSame($outbox, $this->messageCount($database));

    $this->upload($client, $this->png(0, 0, 255));
    self::assertResponseStatusCodeSame(409);
    self::assertStringContainsString('idempotency_conflict', (string) $client->getResponse()->getContent());
    self::assertSame(count(AvatarVariants::SIZES), $storage->writes);
    self::assertSame($pointer, $database->fetchOne('SELECT avatar_storage_key FROM users WHERE id = ?', [self::USER]));

    $database->executeStatement('DELETE FROM role_assignments WHERE id = ?', [self::ASSIGNMENT]);
    $this->upload($client, $bytes);
    self::assertResponseStatusCodeSame(403);
    self::assertFalse($client->getResponse()->headers->has('Idempotency-Replayed'));
    self::assertStringNotContainsString($url, (string) $client->getResponse()->getContent());
    self::assertSame(count(AvatarVariants::SIZES), $storage->writes);
    self::assertSame(0, $storage->deletes);
    self::assertSame($written, $storage->blobs);
    self::assertSame($outbox, $this->messageCount($database));
  }

  private function seed(KernelBrowser $client, EntityManagerInterface $auth): void
  {
    $now = new DateTimeImmutable();
    $user = new UserRecord();
    $user->id = self::USER;
    $user->username = 'avatar-http-idempotency';
    $user->email = 'avatar-http-idempotency@example.test';
    $user->firstName = 'Avatar';
    $user->lastName = 'Replay';
    $user->status = 'active';
    $user->emailVerified = true;
    $user->emailOwnershipVerifiedAt = $now;
    $user->createdAt = $now;
    $auth->persist($user);
    $role = $auth->getRepository(RoleRecord::class)->findOneBy(['name' => 'user']);
    self::assertInstanceOf(RoleRecord::class, $role);
    $assignment = new RoleAssignmentRecord();
    $assignment->id = self::ASSIGNMENT;
    $assignment->roleId = $role->id;
    $assignment->role = $role;
    $assignment->subjectType = 'user';
    $assignment->subjectId = self::USER;
    $assignment->assignedAt = $now;
    $auth->persist($assignment);
    $auth->flush();
    $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer ' . InteractiveTokenFactory::issue(static::getContainer(), self::USER, $user->email));
  }

  private function upload(KernelBrowser $client, string $bytes): void
  {
    $path = tempnam(sys_get_temp_dir(), 'fg_avatar_http_');
    self::assertIsString($path);
    self::assertNotFalse(file_put_contents($path, $bytes));

    try {
      $client->request('PUT', '/api/me/avatar', files: ['avatar' => new UploadedFile($path, 'avatar.png', 'image/png', test: true)], server: [
        'CONTENT_TYPE' => 'multipart/form-data',
        'HTTP_ACCEPT' => 'application/ld+json',
        'HTTP_IDEMPOTENCY_KEY' => 'avatar-offline-upload',
      ]);
    } finally {
      unlink($path);
    }
  }

  private function messageCount(Connection $database): int
  {
    $count = $database->fetchOne('SELECT COUNT(*) FROM messenger_messages');
    self::assertIsInt($count);

    return $count;
  }

  /**
   * @param int<0, 255> $red
   * @param int<0, 255> $green
   * @param int<0, 255> $blue
   */
  private function png(int $red, int $green, int $blue): string
  {
    $image = imagecreatetruecolor(8, 8);
    self::assertNotFalse($image);
    $color = imagecolorallocate($image, $red, $green, $blue);
    self::assertNotFalse($color);
    imagefill($image, 0, 0, $color);
    ob_start();
    imagepng($image);
    $bytes = ob_get_clean();
    imagedestroy($image);
    self::assertIsString($bytes);

    return $bytes;
  }
}
