<?php

declare(strict_types=1);

namespace Tests\Unit\User\Domain\Model\User;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\ValueObject\Email;
use Tests\Helper\TestEventIdProvider;
use User\Domain\Event\UserEmailVerifiedEvent;
use User\Domain\Model\User\User;
use User\Domain\ValueObject\{HashedPassword, Locale, UserId, UserProfile, UserStatus, Username};

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
   * Method testActivateWithVerifiedEmailSetsActiveAndResetsFailedAttempts.
   *
   * Tests that activating a user whose email is verified moves the account to
   * the active status and clears any accumulated failed login attempts.
   *
   * @since 1.0.0
   *
   * @return void no return value
   */
  #[Test]
  public function testActivateWithVerifiedEmailSetsActiveAndResetsFailedAttempts(): void
  {
    $eventIdProvider = new TestEventIdProvider();
    $user = $this->registerUser($eventIdProvider, '550e8400-e29b-41d4-a716-4466554400a1');
    $user->verifyEmail($eventIdProvider);

    // Accumulate a couple of failures (below the lock threshold).
    $user->recordFailedLogin();
    $user->recordFailedLogin();
    $this->assertSame(2, $user->failedLoginAttempts());

    $user->activate();

    $this->assertSame(UserStatus::ACTIVE, $user->status());
    $this->assertSame(0, $user->failedLoginAttempts());
  }

  /**
   * Method testActivateWithUnverifiedEmailStaysPendingVerification.
   *
   * Tests that activating a user whose email is not verified keeps the account
   * in the pending-verification status.
   *
   * @since 1.0.0
   *
   * @return void no return value
   */
  #[Test]
  public function testActivateWithUnverifiedEmailStaysPendingVerification(): void
  {
    $eventIdProvider = new TestEventIdProvider();
    $user = $this->registerUser($eventIdProvider, '550e8400-e29b-41d4-a716-4466554400a2');

    $this->assertFalse($user->isEmailVerified());

    $user->activate();

    $this->assertSame(UserStatus::PENDING_VERIFICATION, $user->status());
    $this->assertSame(0, $user->failedLoginAttempts());
  }

  /**
   * Method testUpdateLocaleChangesPreferredLanguage.
   *
   * Tests that a freshly registered user follows the browser language and can
   * switch to a fixed locale and back to the system preference.
   *
   * @since 1.0.0
   *
   * @return void no return value
   */
  #[Test]
  public function testUpdateLocaleChangesPreferredLanguage(): void
  {
    $eventIdProvider = new TestEventIdProvider();
    $user = $this->registerUser($eventIdProvider, '550e8400-e29b-41d4-a716-4466554400a3');

    // Default is to follow the browser language.
    $this->assertSame(Locale::SYSTEM, $user->locale());

    $user->updateLocale(Locale::FR);
    $this->assertSame(Locale::FR, $user->locale());

    $user->updateLocale(Locale::SYSTEM);
    $this->assertSame(Locale::SYSTEM, $user->locale());
  }

  /**
   * Method testVerifyEmailKeepsNonPendingStatusButStillRecordsEvent.
   *
   * Tests that verifying the email of an account whose status is not pending
   * verification (here: locked) marks the email as verified and records the
   * event without overwriting the existing status.
   *
   * @since 1.0.0
   *
   * @return void no return value
   */
  #[Test]
  public function testVerifyEmailKeepsNonPendingStatusButStillRecordsEvent(): void
  {
    $eventIdProvider = new TestEventIdProvider();
    $user = $this->registerUser($eventIdProvider, '550e8400-e29b-41d4-a716-4466554400a4');

    // Lock the account while the email is still unverified.
    for ($attempt = 0; $attempt < 5; ++$attempt) {
      $user->recordFailedLogin();
    }
    $this->assertSame(UserStatus::LOCKED, $user->status());
    $this->assertFalse($user->isEmailVerified());

    $user->releaseEvents();
    $user->verifyEmail($eventIdProvider);

    $this->assertTrue($user->isEmailVerified());
    $this->assertSame(UserStatus::LOCKED, $user->status());

    $events = $user->releaseEvents();
    $this->assertCount(1, $events);
    $this->assertInstanceOf(UserEmailVerifiedEvent::class, $events[0]);
    $this->assertSame($user->id()->value, $events[0]->aggregateId());
  }

  /**
   * Method registerUser.
   *
   * Registers a fresh, tenant-less user for the branch under test.
   *
   * @since 1.0.0
   *
   * @param TestEventIdProvider $eventIdProvider the event ID provider
   * @param string $id the user UUID
   *
   * @return User the registered user
   */
  private function registerUser(TestEventIdProvider $eventIdProvider, string $id): User
  {
    return User::register(
      id: new UserId($id),
      username: new Username('jdoe'),
      email: new Email('jdoe@example.com'),
      password: HashedPassword::fromPlain('password123'),
      profile: new UserProfile('John', 'Doe'),
      eventIdProvider: $eventIdProvider,
    );
  }
  // #endregion
}
