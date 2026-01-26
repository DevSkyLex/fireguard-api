<?php

declare(strict_types=1);

namespace Tests\Unit\User\Infrastructure\Persistence\Doctrine\Mapper;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\ValueObject\{Email, TenantId};
use Tests\Helper\TestEventIdProvider;
use User\Domain\Model\User\User;
use User\Domain\ValueObject\{HashedPassword, UserId, UserProfile, UserStatus, Username};
use User\Infrastructure\Persistence\Doctrine\Mapper\UserMapper;
use User\Infrastructure\Persistence\Doctrine\Record\UserRecord;

/**
 * Test UserMapperTest.
 *
 * @category Mapper Tests
 */
#[CoversClass(className: UserMapper::class)]
final class UserMapperTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testToRecordMapsUser(): void
  {
    $eventProvider = new TestEventIdProvider();
    $tenantId = TenantId::fromString('123e4567-e89b-12d3-a456-426614174999');
    $hashedPassword = HashedPassword::fromPlain('TestPassword123!');

    $user = User::register(
      id: new UserId('123e4567-e89b-12d3-a456-426614174000'),
      username: new Username('testuser'),
      email: new Email('user@example.com'),
      password: $hashedPassword,
      profile: new UserProfile('Test', 'User', 'https://example.com/avatar.png'),
      eventIdProvider: $eventProvider,
      tenantId: $tenantId,
    );

    $user->verifyEmail($eventProvider);
    $user->recordSuccessfulLogin();
    $user->recordFailedLogin();

    $mapper = new UserMapper();
    $record = $mapper->toRecord($user);

    self::assertSame('123e4567-e89b-12d3-a456-426614174000', $record->id);
    self::assertSame('testuser', $record->username);
    self::assertSame('user@example.com', $record->email);
    self::assertSame('Test', $record->firstName);
    self::assertSame('User', $record->lastName);
    self::assertSame('https://example.com/avatar.png', $record->avatarUrl);
    self::assertSame(UserStatus::ACTIVE->value, $record->status);
    self::assertTrue($record->emailVerified);
    self::assertSame((string) $tenantId, $record->tenantId);
    self::assertInstanceOf(DateTimeImmutable::class, $record->createdAt);
    self::assertInstanceOf(DateTimeImmutable::class, $record->lastLoginAt);
    self::assertSame($hashedPassword->value, $record->password);
    self::assertSame(1, $record->failedLoginAttempts);
  }

  #[Test]
  public function testUpdateRecordUpdatesFields(): void
  {
    $eventProvider = new TestEventIdProvider();
    $hashedPassword = HashedPassword::fromPlain('UpdatedPassword123!');

    $user = User::register(
      id: new UserId('123e4567-e89b-12d3-a456-426614174000'),
      username: new Username('updateduser'),
      email: new Email('updated@example.com'),
      password: $hashedPassword,
      profile: new UserProfile('Updated', 'User', 'https://example.com/updated.png'),
      eventIdProvider: $eventProvider,
    );

    $record = new UserRecord();
    $record->id = '123e4567-e89b-12d3-a456-426614174000';
    $record->username = 'old';
    $record->email = 'old@example.com';
    $record->firstName = 'Old';
    $record->lastName = 'User';
    $record->avatarUrl = null;
    $record->status = UserStatus::INACTIVE->value;
    $record->emailVerified = false;
    $record->tenantId = null;
    $record->createdAt = new DateTimeImmutable('2024-01-01 00:00:00');
    $record->lastLoginAt = null;
    $record->password = 'old-hash';
    $record->failedLoginAttempts = 3;

    $mapper = new UserMapper();
    $mapper->updateRecord($record, $user);

    self::assertSame('updateduser', $record->username);
    self::assertSame('updated@example.com', $record->email);
    self::assertSame('Updated', $record->firstName);
    self::assertSame('User', $record->lastName);
    self::assertSame('https://example.com/updated.png', $record->avatarUrl);
    self::assertSame(UserStatus::PENDING_VERIFICATION->value, $record->status);
    self::assertFalse($record->emailVerified);
    self::assertSame($hashedPassword->value, $record->password);
  }

  #[Test]
  public function testToDomainMapsRecord(): void
  {
    $record = new UserRecord();
    $record->id = '123e4567-e89b-12d3-a456-426614174000';
    $record->username = 'testuser';
    $record->email = 'user@example.com';
    $record->password = HashedPassword::fromPlain('TestPassword123!')->value;
    $record->firstName = 'Test';
    $record->lastName = 'User';
    $record->avatarUrl = 'https://example.com/avatar.png';
    $record->status = UserStatus::ACTIVE->value;
    $record->emailVerified = true;
    $record->tenantId = '123e4567-e89b-12d3-a456-426614174999';
    $record->createdAt = new DateTimeImmutable('2024-01-01 00:00:00');
    $record->lastLoginAt = new DateTimeImmutable('2024-01-02 00:00:00');
    $record->failedLoginAttempts = 2;

    $mapper = new UserMapper();
    $user = $mapper->toDomain($record);

    self::assertSame($record->id, $user->id()->value);
    self::assertSame($record->username, $user->username()->value);
    self::assertSame($record->email, $user->email()->value);
    self::assertSame($record->status, $user->status()->value);
    self::assertTrue($user->isEmailVerified());
    self::assertEquals($record->createdAt, $user->createdAt());
    self::assertEquals($record->lastLoginAt, $user->lastLoginAt());
    self::assertSame($record->failedLoginAttempts, $user->failedLoginAttempts());
  }
  // #endregion
}
