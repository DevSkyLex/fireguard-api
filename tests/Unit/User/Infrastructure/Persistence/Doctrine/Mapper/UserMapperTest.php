<?php

declare(strict_types=1);

namespace Tests\Unit\User\Infrastructure\Persistence\Doctrine\Mapper;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\ValueObject\{Email, TenantId};
use Tests\Helper\TestEventIdProvider;
use User\Domain\Exception\{InvalidPasswordException, InvalidUserException};
use User\Domain\Model\User\User;
use User\Domain\ValueObject\{HashedPassword, Locale, UserId, UserProfile, UserStatus, Username};
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
    $user->recordSignInMethod('google');
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
    self::assertSame('google', $record->lastSignInMethod);
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
    self::assertSame(0, $record->failedLoginAttempts);
    self::assertSame('123e4567-e89b-12d3-a456-426614174000', $record->id);
    self::assertEquals(new DateTimeImmutable('2024-01-01 00:00:00'), $record->createdAt);
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
    $record->lastSignInMethod = 'microsoft';
    $record->failedLoginAttempts = 2;
    $record->locale = Locale::FR->value;
    $record->emailOwnershipVerifiedAt = new DateTimeImmutable('2024-01-03 00:00:00');

    $mapper = new UserMapper();
    $user = $mapper->toDomain($record);

    self::assertSame($record->id, $user->id()->value);
    self::assertSame($record->username, $user->username()->value);
    self::assertSame($record->email, $user->email()->value);
    self::assertSame($record->status, $user->status()->value);
    self::assertTrue($user->isEmailVerified());
    self::assertEquals($record->createdAt, $user->createdAt());
    self::assertEquals($record->lastLoginAt, $user->lastLoginAt());
    self::assertSame('microsoft', $user->lastSignInMethod());
    self::assertSame($record->failedLoginAttempts, $user->failedLoginAttempts());
    self::assertSame(Locale::FR, $user->locale());
    self::assertEquals($record->emailOwnershipVerifiedAt, $user->emailOwnershipVerifiedAt());
    self::assertFalse($user->hasRecordedEvents());

    $restored = $mapper->toRecord($user);
    self::assertSame($record->id, $restored->id);
    self::assertSame($record->username, $restored->username);
    self::assertSame($record->email, $restored->email);
    self::assertSame($record->password, $restored->password);
    self::assertSame($record->status, $restored->status);
    self::assertSame($record->emailVerified, $restored->emailVerified);
    self::assertSame($record->failedLoginAttempts, $restored->failedLoginAttempts);
    self::assertSame($record->tenantId, $restored->tenantId);
    self::assertSame($record->locale, $restored->locale);
    self::assertSame($record->lastSignInMethod, $restored->lastSignInMethod);
    self::assertEquals($record->createdAt, $restored->createdAt);
    self::assertEquals($record->lastLoginAt, $restored->lastLoginAt);
    self::assertEquals($record->emailOwnershipVerifiedAt, $restored->emailOwnershipVerifiedAt);

    self::assertTrue($user->authenticate('TestPassword123!'));
    self::assertSame(0, $user->failedLoginAttempts());
  }

  #[Test]
  public function testRestoredFederatedUserRemainsPasswordless(): void
  {
    $user = User::registerFederated(
      id: new UserId('123e4567-e89b-12d3-a456-426614174002'),
      username: new Username('federated-user'),
      email: new Email('federated@example.com'),
      profile: new UserProfile('Federated', 'User'),
      eventIdProvider: new TestEventIdProvider(),
    );

    $mapper = new UserMapper();
    $restored = $mapper->toDomain($mapper->toRecord($user));

    self::assertFalse($restored->hasPassword());
    self::assertSame(UserStatus::ACTIVE, $restored->status());
    self::assertTrue($restored->isEmailVerified());
    self::assertNull($mapper->toRecord($restored)->password);
    self::assertFalse($restored->hasRecordedEvents());

    $this->expectException(InvalidPasswordException::class);
    $restored->authenticate('any-password');
  }

  #[Test]
  public function testRestoredLockedUserCannotAuthenticate(): void
  {
    $user = User::register(
      id: new UserId('123e4567-e89b-12d3-a456-426614174003'),
      username: new Username('locked-user'),
      email: new Email('locked@example.com'),
      password: HashedPassword::fromPlain('TestPassword123!'),
      profile: new UserProfile('Locked', 'User'),
      eventIdProvider: new TestEventIdProvider(),
    );
    for ($attempt = 0; $attempt < 5; ++$attempt) {
      $user->recordFailedLogin();
    }

    $mapper = new UserMapper();
    $restored = $mapper->toDomain($mapper->toRecord($user));

    self::assertSame(UserStatus::LOCKED, $restored->status());
    self::assertSame(5, $restored->failedLoginAttempts());
    self::assertFalse($restored->hasRecordedEvents());

    $this->expectException(InvalidUserException::class);
    $restored->authenticate('TestPassword123!');
  }
  // #endregion
}
