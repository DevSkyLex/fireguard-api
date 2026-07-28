<?php

declare(strict_types=1);

namespace Tests\Integration\User\Infrastructure\Repository;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Shared\Application\Contract\Sorting\{SortDirection, Sorting};
use Shared\Domain\ValueObject\{Email, TenantId};
use Shared\Infrastructure\Service\UuidEventIdProvider;
use Shared\Infrastructure\Symfony\Adapter\Outbound\UuidGeneratorAdapter;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use User\Domain\Model\User\User;
use User\Domain\ValueObject\{HashedPassword, UserId, UserProfile, Username};
use User\Infrastructure\Persistence\Doctrine\Mapper\UserMapper;
use User\Infrastructure\Persistence\Doctrine\Repository\UserRepository;

use function array_map;
use function count;
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
    $entityManager = $container->get('doctrine.orm.auth_entity_manager');
    $this->entityManager = $entityManager;

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
    // A delta, not an absolute: the seeded baseline already holds users.
    $before = count($this->repository->findAll());

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

    self::assertCount($before + 3, $allUsers);
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

  #[Test]
  public function testFindFilteredAndCountFilteredApplyTheSearchFilter(): void
  {
    $this->repository->save($this->createTestUser('550e8400-e29b-41d4-a716-4466554400a1', 'zzsearchable-alpha', 'zzsearchable-alpha@example.com'));
    $this->repository->save($this->createTestUser('550e8400-e29b-41d4-a716-4466554400a2', 'zzsearchable-bravo', 'zzsearchable-bravo@example.com'));
    $this->repository->save($this->createTestUser('550e8400-e29b-41d4-a716-4466554400a3', 'zzunrelated-charlie', 'zzunrelated-charlie@example.com'));

    $matches = $this->repository->findFiltered(
      'zzsearchable',
      new Sorting('username', SortDirection::ASC),
      20,
      0,
    );

    self::assertSame(
      ['zzsearchable-alpha', 'zzsearchable-bravo'],
      array_map(static fn (User $user): string => $user->username()->value, $matches),
    );
    self::assertSame(2, $this->repository->countFiltered('zzsearchable'));

    // The wildcard characters in the needle are escaped, so they match
    // literally instead of widening the result set.
    self::assertSame(0, $this->repository->countFiltered('%zzsearchable%'));
    self::assertSame(0, $this->repository->countFiltered('zzsearchable_alpha'));
  }

  #[Test]
  public function testFindFilteredSupportsEverySortableField(): void
  {
    $this->repository->save($this->createTestUser('550e8400-e29b-41d4-a716-4466554400b1', 'zzsortable-alpha', 'zzsortable-alpha@example.com'));
    $this->repository->save($this->createTestUser('550e8400-e29b-41d4-a716-4466554400b2', 'zzsortable-bravo', 'zzsortable-bravo@example.com'));

    // Every branch of the sort-field whitelist, plus the createdAt default.
    foreach (['username', 'email', 'firstName', 'lastName', 'status', 'createdAt', 'unmapped-field'] as $field) {
      self::assertCount(
        2,
        $this->repository->findFiltered('zzsortable', new Sorting($field, SortDirection::DESC), 20, 0),
        'Sorting by "' . $field . '" must stay a valid query.',
      );
    }

    $descending = array_map(
      static fn (User $user): string => $user->username()->value,
      $this->repository->findFiltered('zzsortable', new Sorting('username', SortDirection::DESC), 20, 0),
    );

    self::assertSame(['zzsortable-bravo', 'zzsortable-alpha'], $descending);

    $secondPage = $this->repository->findFiltered('zzsortable', new Sorting('username', SortDirection::ASC), 1, 1);

    self::assertCount(1, $secondPage);
    self::assertSame('zzsortable-bravo', $secondPage[0]->username()->value);
  }

  #[Test]
  public function testFindFilteredScopesToTheRequestedTenant(): void
  {
    $tenantId = '00000000-0000-4000-8000-000000000001';
    $this->repository->save($this->createTestUser('550e8400-e29b-41d4-a716-4466554400c1', 'zztenanted-alpha', 'zztenanted-alpha@example.com'));

    self::assertSame(1, $this->repository->countFiltered('zztenanted', $tenantId));
    self::assertCount(1, $this->repository->findFiltered('zztenanted', new Sorting('createdAt'), 20, 0, $tenantId));
    self::assertSame(0, $this->repository->countFiltered('zztenanted', '00000000-0000-4000-8000-0000000000ff'));
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
