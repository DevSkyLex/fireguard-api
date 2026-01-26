<?php

declare(strict_types=1);

namespace Tests\Unit\User\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\{EntityManagerInterface, EntityRepository};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\ValueObject\Email;
use Tests\Helper\TestEventIdProvider;
use User\Domain\Model\User\User;
use User\Domain\ValueObject\{HashedPassword, UserId, UserProfile, Username};
use User\Infrastructure\Persistence\Doctrine\Mapper\UserMapper;
use User\Infrastructure\Persistence\Doctrine\Record\UserRecord;
use User\Infrastructure\Persistence\Doctrine\Repository\UserRepository;

/**
 * Test UserRepositoryTest.
 *
 * @category Repository Tests
 */
#[CoversClass(className: UserRepository::class)]
final class UserRepositoryTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testSaveCreatesNewRecord(): void
  {
    $user = $this->createUser();
    $mapper = new UserMapper();

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::once())
      ->method('find')
      ->with(UserRecord::class, $user->id()->value)
      ->willReturn(null);
    $entityManager->expects(self::once())
      ->method('persist')
      ->with(self::callback(function (UserRecord $record) use ($user): bool {
        return $record->id === $user->id()->value;
      }));
    $entityManager->expects(self::once())
      ->method('flush');

    $repository = new UserRepository(
      entityManager: $entityManager,
      mapper: $mapper,
    );

    $repository->save($user);
  }

  #[Test]
  public function testSaveUpdatesExistingRecord(): void
  {
    $user = $this->createUser();
    $record = new UserRecord();

    $mapper = new UserMapper();

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::once())
      ->method('find')
      ->with(UserRecord::class, $user->id()->value)
      ->willReturn($record);
    $entityManager->expects(self::never())
      ->method('persist');
    $entityManager->expects(self::once())
      ->method('flush');

    $repository = new UserRepository(
      entityManager: $entityManager,
      mapper: $mapper,
    );

    $repository->save($user);

    self::assertSame($user->email()->value, $record->email);
  }

  #[Test]
  public function testFindByIdReturnsNullWhenMissing(): void
  {
    $mapper = new UserMapper();

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $missingId = '123e4567-e89b-12d3-a456-426614174111';

    $entityManager->expects(self::once())
      ->method('find')
      ->with(UserRecord::class, $missingId)
      ->willReturn(null);

    $repository = new UserRepository(
      entityManager: $entityManager,
      mapper: $mapper,
    );

    $result = $repository->findById(new UserId($missingId));

    self::assertNull($result);
  }

  #[Test]
  public function testFindByEmailUsesMapper(): void
  {
    $user = $this->createUser();
    $record = $this->createUserRecord();

    $doctrineRepository = $this->createMock(EntityRepository::class);
    $doctrineRepository->expects(self::once())
      ->method('findOneBy')
      ->with(['email' => $user->email()->value])
      ->willReturn($record);

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::once())
      ->method('getRepository')
      ->with(UserRecord::class)
      ->willReturn($doctrineRepository);

    $mapper = new UserMapper();

    $repository = new UserRepository(
      entityManager: $entityManager,
      mapper: $mapper,
    );

    $result = $repository->findByEmail(new Email($user->email()->value));

    self::assertInstanceOf(User::class, $result);
  }

  #[Test]
  public function testExistsByUsernameReturnsTrue(): void
  {
    $doctrineRepository = $this->createMock(EntityRepository::class);
    $doctrineRepository->expects(self::once())
      ->method('count')
      ->with(['username' => 'testuser'])
      ->willReturn(1);

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::once())
      ->method('getRepository')
      ->with(UserRecord::class)
      ->willReturn($doctrineRepository);

    $repository = new UserRepository(
      entityManager: $entityManager,
      mapper: new UserMapper(),
    );

    self::assertTrue($repository->existsByUsername(new Username('testuser')));
  }

  #[Test]
  public function testDeleteRemovesReference(): void
  {
    $user = $this->createUser();
    $record = new UserRecord();

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::once())
      ->method('getReference')
      ->with(UserRecord::class, $user->id()->value)
      ->willReturn($record);
    $entityManager->expects(self::once())
      ->method('remove')
      ->with($record);
    $entityManager->expects(self::once())
      ->method('flush');

    $repository = new UserRepository(
      entityManager: $entityManager,
      mapper: new UserMapper(),
    );

    $repository->delete($user);
  }

  private function createUser(): User
  {
    return User::register(
      id: new UserId('123e4567-e89b-12d3-a456-426614174000'),
      username: new Username('testuser'),
      email: new Email('user@example.com'),
      password: HashedPassword::fromPlain('TestPassword123!'),
      profile: new UserProfile('Test', 'User'),
      eventIdProvider: new TestEventIdProvider(),
    );
  }

  private function createUserRecord(): UserRecord
  {
    $record = new UserRecord();
    $record->id = '123e4567-e89b-12d3-a456-426614174000';
    $record->username = 'testuser';
    $record->email = 'user@example.com';
    $record->password = HashedPassword::fromPlain('TestPassword123!')->value;
    $record->firstName = 'Test';
    $record->lastName = 'User';
    $record->avatarUrl = null;
    $record->status = 'active';
    $record->emailVerified = true;
    $record->tenantId = null;
    $record->createdAt = new DateTimeImmutable('2024-01-01 00:00:00');
    $record->lastLoginAt = null;
    $record->failedLoginAttempts = 0;

    return $record;
  }
  // #endregion
}
