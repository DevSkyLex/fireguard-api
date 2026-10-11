<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Infrastructure\Security\User\SecurityUser;
use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Organization\Application\Contract\Logo\OrganizationLogoStorageKey;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRecord, OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};
use PHPUnit\Framework\Attributes\Test;
use Shared\Application\Port\Outbound\FileStoragePort;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Tests\Support\Database\RootCommitDatabaseIsolationTrait;
use Tests\Support\Image\ImageFixtures;

use function file_put_contents;
use function imagecreatetruecolor;
use function imagepng;
use function json_decode;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

use const JSON_THROW_ON_ERROR;

/** Verifies the real final commit and public image contract on disposable PostgreSQL clones. */
#[SkipDatabaseRollback]
final class OrganizationLogoApiTest extends WebTestCase
{
  use RootCommitDatabaseIsolationTrait;

  private const string ORGANIZATION = 'bd000000-0000-4000-8000-000000000091';

  private const string OWNER = 'bd000000-0000-4000-8000-000000000092';

  #[Test]
  public function uploadCommitsAnImmutableLogoAndAnonymousReadPreservesItsCacheHeaders(): void
  {
    $client = self::createClient();
    $this->seed();
    $this->login($client, self::OWNER);
    $path = $this->png();

    try {
      $client->request('POST', '/api/organizations/' . self::ORGANIZATION . '/logo', files: ['logo' => new UploadedFile($path, 'logo.png', 'image/png', test: true)]);
      self::assertResponseIsSuccessful();
      $output = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
      self::assertIsArray($output);
      self::assertIsString($output['logoUrl']);
      $url = $output['logoUrl'];
      self::assertStringContainsString('/logo.webp?v=', $url);
      self::ensureKernelShutdown();
      $anonymous = self::createClient();
      $anonymous->request('GET', '/api/organizations/' . self::ORGANIZATION . '/logo.webp');
      self::assertResponseIsSuccessful();
      self::assertResponseHeaderSame('Content-Type', 'image/webp');
      self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
      self::assertStringContainsString('public', (string) $anonymous->getResponse()->headers->get('Cache-Control'));
      self::assertStringContainsString('max-age=86400', (string) $anonymous->getResponse()->headers->get('Cache-Control'));
      $this->files()->delete(OrganizationLogoStorageKey::fromUrl(self::ORGANIZATION, $url));
    } finally {
      unlink($path);
    }
  }

  #[Test]
  public function permissionDenialPrecedesMultipartValidation(): void
  {
    $client = self::createClient();
    $this->seed();
    $this->login($client, 'bd000000-0000-4000-8000-000000000099');
    $client->request('POST', '/api/organizations/' . self::ORGANIZATION . '/logo');
    self::assertResponseStatusCodeSame(403);
  }

  #[Test]
  public function aRejectedImageLeavesThePreviousUrlAndBytesIntact(): void
  {
    $client = self::createClient();
    $organization = $this->seed();
    $oldUrl = 'https://app.test/api/organizations/' . self::ORGANIZATION . '/logo.webp?v=1726000000';
    $organization->logoUrl = $oldUrl;
    $this->manager()->flush();
    $oldPath = OrganizationLogoStorageKey::fromUrl(self::ORGANIZATION, $oldUrl);
    $this->files()->write($oldPath, 'original');
    $this->login($client, self::OWNER);
    $path = tempnam(sys_get_temp_dir(), 'fg-logo-functional-');
    self::assertIsString($path);
    file_put_contents($path, ImageFixtures::pngHeader(2048, 2049));

    try {
      $client->request('POST', '/api/organizations/' . self::ORGANIZATION . '/logo', files: ['logo' => new UploadedFile($path, 'logo.png', 'image/png', test: true)]);
      self::assertResponseStatusCodeSame(422);
      self::assertSame($oldUrl, $this->manager()->getConnection()->fetchOne('SELECT logo_url FROM organizations WHERE id = ?', [self::ORGANIZATION]));
      self::assertSame('original', $this->files()->read($oldPath));
    } finally {
      $this->files()->delete($oldPath);
      unlink($path);
    }
  }

  private function seed(): OrganizationRecord
  {
    $manager = $this->manager();
    $now = new DateTimeImmutable();
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION;
    $organization->name = 'Immutable logo';
    $organization->slug = 'immutable-logo-regression';
    $organization->ownerUserId = self::OWNER;
    $organization->createdByUserId = self::OWNER;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;
    $manager->persist($organization);
    $role = new OrganizationRoleRecord();
    $role->id = 'bd000000-0000-4000-8000-000000000093';
    $role->organization = $organization;
    $role->name = 'logo-administrator';
    $role->permissions = ['*'];
    $role->createdAt = $now;
    $manager->persist($role);
    $member = new OrganizationMemberRecord();
    $member->id = 'bd000000-0000-4000-8000-000000000094';
    $member->organization = $organization;
    $member->userId = self::OWNER;
    $member->joinedAt = $now;
    $manager->persist($member);
    $assignment = new OrganizationMemberRoleRecord();
    $assignment->member = $member;
    $assignment->role = $role;
    $assignment->assignedAt = $now;
    $manager->persist($assignment);
    $manager->flush();

    return $organization;
  }

  private function manager(): EntityManagerInterface
  {
    /** @var EntityManagerInterface $manager */
    $manager = self::getContainer()->get('doctrine.orm.main_entity_manager');

    return $manager;
  }

  private function files(): FileStoragePort
  {
    /** @var FileStoragePort $files */
    $files = self::getContainer()->get(FileStoragePort::class);

    return $files;
  }

  private function login(KernelBrowser $client, string $id): void
  {
    $client->loginUser(new SecurityUser(id: $id, email: 'logo@example.test', password: 'unused', roles: ['ROLE_USER']), 'api');
  }

  private function png(): string
  {
    $path = tempnam(sys_get_temp_dir(), 'fg-logo-functional-');
    self::assertIsString($path);
    $image = imagecreatetruecolor(2, 2);
    self::assertNotFalse($image);
    imagepng($image, $path);

    return $path;
  }
}
