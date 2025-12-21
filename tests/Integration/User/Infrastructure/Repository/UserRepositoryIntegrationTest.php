<?php

declare(strict_types=1);

namespace Tests\Integration\User\Infrastructure\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Shared\Domain\ValueObject\Email;
use Shared\Domain\ValueObject\TenantId;
use Shared\Infrastructure\Service\UuidEventIdProvider;
use Shared\Infrastructure\Symfony\Adapter\Outbound\UuidGeneratorAdapter;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Throwable;
use User\Domain\Model\User;
use User\Domain\ValueObject\HashedPassword;
use User\Domain\ValueObject\UserId;
use User\Domain\ValueObject\Username;
use User\Domain\ValueObject\UserProfile;
use User\Infrastructure\Persistence\Doctrine\Mapper\UserMapper;
use User\Infrastructure\Persistence\Doctrine\Repository\UserRepository;

use function password_hash;
use function sprintf;

use const PASSWORD_BCRYPT;

/**
 * Test UserRepositoryIntegrationTest.
 *
 * @category Integration Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: UserRepository::class)]
final class UserRepositoryIntegrationTest extends KernelTestCase
{
  // #region Properties
  private EntityManagerInterface $entityManager;

  private UserRepository $repository;
  // #endregion

  // #region Setup
  protected function setUp(): void
  {
    self::bootKernel();
    $container = static::getContainer();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = $container->get('doctrine.orm.entity_manager');
    $this->entityManager = $entityManager;

    // Create schema
    $schemaTool = new SchemaTool($this->entityManager);
    $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();

    try {
      $schemaTool->dropSchema($metadata);
    } catch (Throwable) {
      // Schema might not exist
    }

    $schemaTool->createSchema($metadata);

    /** @var UserMapper $mapper */
    $mapper = $container->get(UserMapper::class);
    $this->repository = new UserRepository($this->entityManager, $mapper);
  }

  protected function tearDown(): void
  {
    parent::tearDown();
    $this->entityManager->close();
    // No need to set to null if not nullable, or we should use @var to satisfy PHPStan if we must
  }
  // #endregion

  // #region Tests
  #[Test]
  public function testSaveAndFindById(): void
  {
    $user = $this->createTestUser('550e8400-e29b-41d4-a716-446655440001', 'testuser1', 'test1@example.com');

    $this->repository->save($user);

    $foundUser = $this->repository->findById(new UserId('550e8400-e29b-41d4-a716-446655440001'));

    self::assertNotNull($foundUser);
    self::assertSame('550e8400-e29b-41d4-a716-446655440001', $foundUser->id()->value);
    self::assertSame('testuser1', $foundUser->username()->value);
    self::assertSame('test1@example.com', $foundUser->email()->value);
  }

  #[Test]
  public function testFindByIdReturnsNullWhenNotFound(): void
  {
    $foundUser = $this->repository->findById(new UserId('00000000-0000-4000-8000-000000000000'));

    self::assertNull($foundUser);
  }

  #[Test]
  public function testFindByUsername(): void
  {
    $user = $this->createTestUser('550e8400-e29b-41d4-a716-446655440002', 'uniqueuser', 'unique@example.com');
    $this->repository->save($user);

    $foundUser = $this->repository->findByUsername(new Username('uniqueuser'));

    self::assertNotNull($foundUser);
    self::assertSame('uniqueuser', $foundUser->username()->value);
  }

  #[Test]
  public function testFindByUsernameReturnsNullWhenNotFound(): void
  {
    $foundUser = $this->repository->findByUsername(new Username('nonexistent'));

    self::assertNull($foundUser);
  }

  #[Test]
  public function testFindByEmail(): void
  {
    $user = $this->createTestUser('550e8400-e29b-41d4-a716-446655440003', 'emailuser', 'findme@example.com');
    $this->repository->save($user);

    $foundUser = $this->repository->findByEmail(new Email('findme@example.com'));

    self::assertNotNull($foundUser);
    self::assertSame('findme@example.com', $foundUser->email()->value);
  }

  #[Test]
  public function testFindByEmailReturnsNullWhenNotFound(): void
  {
    $foundUser = $this->repository->findByEmail(new Email('notfound@example.com'));

    self::assertNull($foundUser);
  }

  #[Test]
  public function testExistsByUsername(): void
  {
    $user = $this->createTestUser('550e8400-e29b-41d4-a716-446655440004', 'existsuser', 'exists@example.com');
    $this->repository->save($user);

    self::assertTrue($this->repository->existsByUsername(new Username('existsuser')));
    self::assertFalse($this->repository->existsByUsername(new Username('doesnotexist')));
  }

  #[Test]
  public function testExistsByEmail(): void
  {
    $user = $this->createTestUser('550e8400-e29b-41d4-a716-446655440005', 'emailexists', 'emailexists@example.com');
    $this->repository->save($user);

    self::assertTrue($this->repository->existsByEmail(new Email('emailexists@example.com')));
    self::assertFalse($this->repository->existsByEmail(new Email('notexists@example.com')));
  }

  #[Test]
  public function testSaveUpdatesExistingUser(): void
  {
    $user = $this->createTestUser('550e8400-e29b-41d4-a716-446655440006', 'updateuser', 'update@example.com');
    $this->repository->save($user);

    // Retrieve and verify email
    $foundUser = $this->repository->findById(new UserId('550e8400-e29b-41d4-a716-446655440006'));
    self::assertNotNull($foundUser);
    self::assertFalse($foundUser->isEmailVerified());

    // Verify email
    $eventIdProvider = new UuidEventIdProvider(new UuidGeneratorAdapter());
    $foundUser->verifyEmail($eventIdProvider);
    $this->repository->save($foundUser);

    // Verify update
    $updatedUser = $this->repository->findById(new UserId('550e8400-e29b-41d4-a716-446655440006'));
    self::assertNotNull($updatedUser);
    self::assertTrue($updatedUser->isEmailVerified());
  }

  #[Test]
  public function testDelete(): void
  {
    $user = $this->createTestUser('550e8400-e29b-41d4-a716-446655440007', 'deleteuser', 'delete@example.com');
    $this->repository->save($user);

    // Verify exists
    $foundUser = $this->repository->findById(new UserId('550e8400-e29b-41d4-a716-446655440007'));
    self::assertNotNull($foundUser);

    // Delete
    $this->repository->delete($foundUser);

    // Verify deleted
    $deletedUser = $this->repository->findById(new UserId('550e8400-e29b-41d4-a716-446655440007'));
    self::assertNull($deletedUser);
  }

  #[Test]
  public function testFindAll(): void
  {
    // Create multiple users
    for ($i = 1; $i <= 3; ++$i) {
      $user = $this->createTestUser(
        sprintf('550e8400-e29b-41d4-a716-44665544000%d', $i),
        "alluser$i",
        "all$i@example.com",
      );
      $this->repository->save($user);
    }

    $allUsers = $this->repository->findAll();

    self::assertCount(3, $allUsers);
  }

  #[Test]
  public function testUserWithProfile(): void
  {
    $eventIdProvider = new UuidEventIdProvider(new UuidGeneratorAdapter());
    $user = User::register(
      id: new UserId('550e8400-e29b-41d4-a716-446655440010'),
      username: new Username('profileuser'),
      email: new Email('profile@example.com'),
      password: new HashedPassword(password_hash('password', PASSWORD_BCRYPT)),
      profile: new UserProfile('John', 'Doe'),
      tenantId: TenantId::fromString('00000000-0000-4000-8000-000000000001'),
      eventIdProvider: $eventIdProvider,
    );
    $user->releaseEvents();

    $this->repository->save($user);

    $foundUser = $this->repository->findById(new UserId('550e8400-e29b-41d4-a716-446655440010'));
    self::assertNotNull($foundUser);
    self::assertSame('John', $foundUser->profile()->firstName);
    self::assertSame('Doe', $foundUser->profile()->lastName);
  }
  // #endregion

  // #region Helpers
  private function createTestUser(string $id, string $username, string $email): User
  {
    $eventIdProvider = new UuidEventIdProvider(new UuidGeneratorAdapter());
    $user = User::register(
      id: new UserId($id),
      username: new Username($username),
      email: new Email($email),
      password: new HashedPassword(password_hash('password123', PASSWORD_BCRYPT)),
      profile: new UserProfile('Test', 'User'),
      tenantId: TenantId::fromString('00000000-0000-4000-8000-000000000001'),
      eventIdProvider: $eventIdProvider,
    );
    $user->releaseEvents();

    return $user;
  }
  // #endregion
}
