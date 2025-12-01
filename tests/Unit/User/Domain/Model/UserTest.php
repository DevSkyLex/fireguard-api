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
    $id = UserId::generate();
    $username = new Username('jdoe');
    $email = new Email('jdoe@example.com');
    $password = HashedPassword::fromPlain('password123');
    $profile = new UserProfile('John', 'Doe');
    $tenantId = new TenantId(Uuid::generate());

    // Act
    $user = User::register(
      $id,
      $username,
      $email,
      $password,
      $profile,
      $tenantId
    );

    // Assert
    $this->assertTrue($user->id()->equals($id));
    $this->assertTrue($user->username()->equals($username));
    $this->assertTrue($user->email()->equals($email));
    $this->assertTrue($user->profile()->equals($profile));
    $this->assertEquals(UserStatus::PENDING_VERIFICATION, $user->status());
    $this->assertFalse($user->isEmailVerified());
    $this->assertTrue($user->tenantId()->equals($tenantId));
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
    $user = User::register(
      UserId::generate(),
      new Username('jdoe'),
      new Email('jdoe@example.com'),
      HashedPassword::fromPlain('password123'),
      new UserProfile('John', 'Doe')
    );
    $user->verifyEmail();

    // Act
    $user->authenticate('password123');

    // Assert
    $this->assertNotNull($user->lastLoginAt());
    $this->assertEquals(0, $user->failedLoginAttempts());
  }
  //#endregion
}
