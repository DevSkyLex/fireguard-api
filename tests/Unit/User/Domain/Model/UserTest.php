<?php

declare(strict_types=1);

namespace Tests\Unit\User\Domain\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Domain\ValueObject\Email;
use Shared\Domain\ValueObject\TenantId;
use Shared\Domain\ValueObject\Uuid;
use User\Domain\Event\UserCreatedEvent;
use User\Domain\Model\User;
use User\Domain\ValueObject\HashedPassword;
use User\Domain\ValueObject\UserId;
use User\Domain\ValueObject\Username;
use User\Domain\ValueObject\UserProfile;
use User\Domain\ValueObject\UserStatus;
use Tests\Helper\TestEventIdProvider;

/**
 * Test UserTest
 * @final
 *
 * Unit tests for the User Aggregate.
 *
 * @category Model Tests
 * @package Tests\Unit\User\Domain\Model
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(User::class)]
final class UserTest extends TestCase
{
  //#region Methods
  /**
   * Method testCanBeRegistered
   *
   * Tests that a user can be registered
   * with all required information.
   *
   * @access public
   * @since 1.0.0
   *
   * @return void No return value.
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
    $this->assertInstanceOf(\DateTimeImmutable::class, $user->createdAt());
    $this->assertNull($user->lastLoginAt());
    $this->assertEquals(0, $user->failedLoginAttempts());

    // Verify Event
    $events = $user->releaseEvents();
    $this->assertCount(1, $events);
    $this->assertInstanceOf(UserCreatedEvent::class, $events[0]);
    $this->assertEquals($id->value, $events[0]->aggregateId());
  }

  /**
   * Method testCanAuthenticate
   *
   * Tests that a user can authenticate
   * with valid credentials.
   *
   * @access public
   * @since 1.0.0
   *
   * @return void No return value.
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
  //#endregion
}
