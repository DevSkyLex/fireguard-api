<?php

declare(strict_types=1);

namespace Tests\Unit\User\Domain\Model;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\ValueObject\{Email, TenantId};
use Tests\Helper\TestEventIdProvider;
use User\Domain\Event\UserCreatedEvent;
use User\Domain\Exception\{InvalidPasswordException, InvalidUserException};
use User\Domain\Model\User\User;
use User\Domain\ValueObject\{HashedPassword, UserId, UserProfile, UserStatus, Username};

/**
 * Test UserTest.
 *
 * @category Model Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(User::class)]
final class UserTest extends TestCase
{
  // #region Methods
  /**
   * Method testCanBeRegistered.
   *
   * Tests that a user can be registered
   * with all required information.
   *
   * @since 1.0.0
   *
   * @return void no return value
   */
  #[Test]
  public function testCanBeRegistered(): void
  {
    // Arrange
    $id = new UserId('550e8400-e29b-41d4-a716-446655440000');
    $username = new Username('jdoe');
    $email = new Email('jdoe@example.com');
    $password = HashedPassword::fromPlain('password123');
    $profile = new UserProfile('John', 'Doe');
    $eventIdProvider = new TestEventIdProvider();
    $tenantId = TenantId::fromString('550e8400-e29b-41d4-a716-446655440001');

    // Act
    $user = User::register(
      id: $id,
      username: $username,
      email: $email,
      password: $password,
      profile: $profile,
      eventIdProvider: $eventIdProvider,
      tenantId: $tenantId,
    );

    // Assert
    $this->assertTrue($user->id()->equals($id));
    $this->assertTrue($user->username()->equals($username));
    $this->assertTrue($user->email()->equals($email));
    $this->assertTrue($user->profile()->equals($profile));
    $this->assertEquals(UserStatus::PENDING_VERIFICATION, $user->status());
    $this->assertFalse($user->isEmailVerified());
    $tenant = $user->tenantId();
    $this->assertNotNull($tenant);
    $this->assertTrue($tenant->equals($tenantId));
    $this->assertInstanceOf(DateTimeImmutable::class, $user->createdAt());
    $this->assertNull($user->lastLoginAt());
    $this->assertEquals(0, $user->failedLoginAttempts());

    // Verify Event
    $events = $user->releaseEvents();
    $this->assertCount(1, $events);
    $this->assertInstanceOf(UserCreatedEvent::class, $events[0]);
    $this->assertEquals($id->value, $events[0]->aggregateId());
  }

  /**
   * Method testCanAuthenticate.
   *
   * Tests that a user can authenticate
   * with valid credentials.
   *
   * @since 1.0.0
   *
   * @return void no return value
   */
  #[Test]
  public function testCanAuthenticate(): void
  {
    // Arrange
    $eventIdProvider = new TestEventIdProvider();
    $user = User::register(
      id: new UserId('550e8400-e29b-41d4-a716-446655440002'),
      username: new Username('jdoe'),
      email: new Email('jdoe@example.com'),
      password: HashedPassword::fromPlain('password123'),
      profile: new UserProfile('John', 'Doe'),
      eventIdProvider: $eventIdProvider,
    );
    $user->verifyEmail($eventIdProvider);

    // Act
    $user->authenticate('password123');

    // Assert
    $this->assertNotNull($user->lastLoginAt());
    $this->assertEquals(0, $user->failedLoginAttempts());
  }

  #[Test]
  public function testVerifyEmailIsIdempotent(): void
  {
    $eventIdProvider = new TestEventIdProvider();
    $user = User::register(
      id: new UserId('550e8400-e29b-41d4-a716-446655440003'),
      username: new Username('jdoe'),
      email: new Email('jdoe@example.com'),
      password: HashedPassword::fromPlain('password123'),
      profile: new UserProfile('John', 'Doe'),
      eventIdProvider: $eventIdProvider,
    );

    $user->releaseEvents();
    $user->verifyEmail($eventIdProvider);
    $events = $user->releaseEvents();

    $this->assertCount(1, $events);
    $user->verifyEmail($eventIdProvider);

    $this->assertSame(UserStatus::ACTIVE, $user->status());
    $this->assertTrue($user->isEmailVerified());
    $this->assertCount(0, $user->releaseEvents());
  }

  #[Test]
  public function testAuthenticateThrowsOnInvalidPasswordAndLocksAccount(): void
  {
    $eventIdProvider = new TestEventIdProvider();
    $user = User::register(
      id: new UserId('550e8400-e29b-41d4-a716-446655440004'),
      username: new Username('jdoe'),
      email: new Email('jdoe@example.com'),
      password: HashedPassword::fromPlain('password123'),
      profile: new UserProfile('John', 'Doe'),
      eventIdProvider: $eventIdProvider,
    );
    $user->verifyEmail($eventIdProvider);

    for ($attempt = 0; $attempt < 5; ++$attempt) {
      try {
        $user->authenticate('wrong-password');
        $this->fail('Expected InvalidPasswordException');
      } catch (InvalidPasswordException) {
        // expected
      }
    }

    $this->assertSame(UserStatus::LOCKED, $user->status());
    $this->expectException(InvalidUserException::class);

    $user->authenticate('password123');
  }

  #[Test]
  public function testUpdateProfileAndChangePassword(): void
  {
    $eventIdProvider = new TestEventIdProvider();
    $user = User::register(
      id: new UserId('550e8400-e29b-41d4-a716-446655440005'),
      username: new Username('jdoe'),
      email: new Email('jdoe@example.com'),
      password: HashedPassword::fromPlain('password123'),
      profile: new UserProfile('John', 'Doe'),
      eventIdProvider: $eventIdProvider,
    );

    $newProfile = new UserProfile('Jane', 'Doe');
    $user->updateProfile($newProfile);
    $this->assertTrue($user->profile()->equals($newProfile));

    $user->changePassword(HashedPassword::fromPlain('new-pass'));
    $user->verifyEmail($eventIdProvider);
    $user->authenticate('new-pass');

    $this->assertEquals(0, $user->failedLoginAttempts());
  }
  // #endregion
}
