<?php

declare(strict_types=1);

namespace User\Domain\Model;

use DateTimeImmutable;
use Shared\Domain\Service\EventIdProvider;
use Shared\Domain\Trait\RecordsDomainEvents;
use Shared\Domain\ValueObject\Email;
use Shared\Domain\ValueObject\TenantId;
use User\Domain\Event\UserEmailVerifiedEvent;
use User\Domain\Event\UserCreatedEvent;
use User\Domain\Exception\InvalidPasswordException;
use User\Domain\Exception\InvalidUserException;
use User\Domain\ValueObject\HashedPassword;
use User\Domain\ValueObject\UserId;
use User\Domain\ValueObject\Username;
use User\Domain\ValueObject\UserProfile;
use User\Domain\ValueObject\UserStatus;

/**
 * Aggregate User
 * @final
 *
 * Represents a user in the system.
 * This is the aggregate root for the User bounded context.
 *
 * @category Model
 * @package User\Domain\Model
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class User
{
  //#region Traits
  /**
   * Trait RecordsDomainEvents
   *
   *
   */
  use RecordsDomainEvents;
  //#endregion

  //#region Constants
  /**
   * Constant MAX_FAILED_LOGIN_ATTEMPTS
   *
   * Maximum number of failed login attempts
   * before locking the account.
   *
   * @access private
   * @since 1.0.0
   *
   * @var int
   */
  private const int MAX_FAILED_LOGIN_ATTEMPTS = 5;
  //#endregion

  //#region Constructor
  /**
   * Constructor
   *
   * Initialize the User model
   *
   * @access public
   * @since 1.0.0
   *
   * @param UserId $id The user ID.
   * @param Username $username The username.
   * @param Email $email The user email.
   * @param HashedPassword $password The hashed password.
   * @param UserProfile $profile The user profile.
   * @param UserStatus $status The user status.
   * @param bool $emailVerified Whether the email is verified.
   * @param TenantId|null $tenantId The tenant ID (for multi-tenant).
   * @param DateTimeImmutable $createdAt When the user was created.
   * @param DateTimeImmutable|null $lastLoginAt When the user last logged in.
   * @param int $failedLoginAttempts Number of failed login attempts.
   */
  private function __construct(
    private UserId $id,
    private Username $username,
    private Email $email,
    private HashedPassword $password,
    private UserProfile $profile,
    private UserStatus $status,
    private bool $emailVerified,
    private ?TenantId $tenantId,
    private DateTimeImmutable $createdAt,
    private ?DateTimeImmutable $lastLoginAt = null,
    private int $failedLoginAttempts = 0,
  ) {
  }
  //#endregion

  //#region Methods
  /**
   * Method register
   * @static
   *
   * Factory method to register a new user.
   *
   * @access public
   * @since 1.0.0
   *
   * @param UserId $id The user ID.
   * @param Username $username The username.
   * @param Email $email The user email.
   * @param HashedPassword $password The hashed password.
   * @param UserProfile $profile The user profile.
   * @param EventIdProvider $eventIdProvider The event ID provider.
   * @param TenantId|null $tenantId The tenant ID.
   *
   * @return self The new user instance.
   */
  public static function register(
    UserId $id,
    Username $username,
    Email $email,
    HashedPassword $password,
    UserProfile $profile,
    EventIdProvider $eventIdProvider,
    ?TenantId $tenantId = null,
  ): self {
    $user = new self(
      id: $id,
      username: $username,
      email: $email,
      password: $password,
      profile: $profile,
      status: UserStatus::PENDING_VERIFICATION,
      emailVerified: false,
      tenantId: $tenantId,
      createdAt: new DateTimeImmutable(),
    );

    $user->recordEvent(new UserCreatedEvent(
      eventId: $eventIdProvider->nextEventId(),
      userId: $id->value,
      username: $username->value,
      email: $email->value,
      occurredAt: new DateTimeImmutable(),
    ));

    return $user;
  }

  /**
   * Method verifyEmail
   *
   * Marks the user's email as verified and activates the account.
   *
   * @access public
   * @since 1.0.0
   *
   * @param EventIdProvider $eventIdProvider The event ID provider.
   *
   * @return void No return value
   */
  public function verifyEmail(EventIdProvider $eventIdProvider): void
  {
    if ($this->emailVerified)
      return;

    $this->emailVerified = true;
    $this->status = UserStatus::ACTIVE;

    $this->recordEvent(event: new UserEmailVerifiedEvent(
      eventId: $eventIdProvider->nextEventId(),
      userId: $this->id->value,
      email: $this->email->value,
      occurredAt: new DateTimeImmutable(),
    ));
  }

  /**
   * Method authenticate
   *
   * Authenticates the user with a password.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $plainPassword The plain text password.
   *
   * @return bool True if authentication successful.
   * @throws InvalidUserException If the user cannot login.
   * @throws InvalidPasswordException If the password is incorrect.
   */
  public function authenticate(string $plainPassword): bool
  {
    if (!$this->canLogin())
      throw InvalidUserException::cannotLogin(
        reason: $this->status->label()
      );

    if (!$this->password->verify(plain: $plainPassword)) {
      $this->recordFailedLogin();
      throw InvalidPasswordException::incorrect();
    }

    $this->recordSuccessfulLogin();
    return true;
  }

  /**
   * Method recordSuccessfulLogin
   *
   * Records a successful login
   * attempt.
   *
   * @access public
   * @since 1.0.0
   *
   * @return void No return value
   */
  public function recordSuccessfulLogin(): void
  {
    $this->lastLoginAt = new DateTimeImmutable();
    $this->failedLoginAttempts = 0;
  }

  /**
   * Method recordFailedLogin
   *
   * Records a failed login attempt and
   * locks account if threshold exceeded.
   *
   * @access public
   * @since 1.0.0
   *
   * @return void No return value
   */
  public function recordFailedLogin(): void
  {
    $this->failedLoginAttempts++;

    if ($this->failedLoginAttempts >= self::MAX_FAILED_LOGIN_ATTEMPTS) {
      $this->status = UserStatus::LOCKED;
    }
  }

  /**
   * Method canLogin
   *
   * Checks if the user can login.
   *
   * @access public
   * @since 1.0.0
   *
   * @return bool True if the user can login.
   */
  public function canLogin(): bool
  {
    return $this->status->canLogin();
  }

  /**
   * Method updateProfile
   *
   * Updates the user's profile.
   *
   * @access public
   * @since 1.0.0
   *
   * @param UserProfile $profile The new profile.
   *
   * @return void No return value
   */
  public function updateProfile(UserProfile $profile): void
  {
    $this->profile = $profile;
  }

  /**
   * Method changePassword
   *
   * Changes the user's password.
   *
   * @access public
   * @since 1.0.0
   *
   * @param HashedPassword $newPassword The new hashed password.
   *
   * @return void No return value
   */
  public function changePassword(HashedPassword $newPassword): void
  {
    $this->password = $newPassword;
  }

  /**
   * Method id
   *
   * Get the user ID.
   *
   * @return UserId
   */
  public function id(): UserId
  {
    return $this->id;
  }

  /**
   * Get the username.
   *
   * @return Username
   */
  public function username(): Username
  {
    return $this->username;
  }

  /**
   * Get the user email.
   *
   * @return Email
   */
  public function email(): Email
  {
    return $this->email;
  }

  /**
   * Get the user profile.
   *
   * @return UserProfile
   */
  public function profile(): UserProfile
  {
    return $this->profile;
  }

  /**
   * Get the user status.
   *
   * @return UserStatus
   */
  public function status(): UserStatus
  {
    return $this->status;
  }

  /**
   * Check if the email is verified.
   *
   * @return bool
   */
  public function isEmailVerified(): bool
  {
    return $this->emailVerified;
  }

  /**
   * Get the tenant ID.
   *
   * @return TenantId|null
   */
  public function tenantId(): ?TenantId
  {
    return $this->tenantId;
  }

  /**
   * Get the creation date.
   *
   * @return DateTimeImmutable
   */
  public function createdAt(): DateTimeImmutable
  {
    return $this->createdAt;
  }

  /**
   * Get the last login date.
   *
   * @return DateTimeImmutable|null
   */
  public function lastLoginAt(): ?DateTimeImmutable
  {
    return $this->lastLoginAt;
  }

  /**
   * Get the number of failed login attempts.
   *
   * @return int
   */
  public function failedLoginAttempts(): int
  {
    return $this->failedLoginAttempts;
  }
  //#endregion
}
