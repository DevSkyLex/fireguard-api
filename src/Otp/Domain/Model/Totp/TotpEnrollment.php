<?php

declare(strict_types=1);

namespace Otp\Domain\Model\Totp;

use DateInterval;
use DateTimeImmutable;
use Otp\Domain\Exception\{
  TotpDisableTemporarilyLockedException,
  TotpEnrollmentMaxAttemptsException,
  TotpEnrollmentNoPendingSecretException,
  TotpEnrollmentNotActiveException,
};
use Otp\Domain\ValueObject\TotpSecret;

use function max;

/**
 * Model TotpEnrollment.
 *
 * Aggregate for a user's TOTP (authenticator app) enrollment. A user has at
 * most one enrollment, holding an optional "pending" secret (awaiting
 * confirmation) and an optional "active" secret (confirmed and usable for
 * login MFA). Calling setup again only replaces the pending secret; the
 * active secret (if any) stays usable until the new one is confirmed.
 *
 * @category Model
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class TotpEnrollment
{
  // #region Constants
  /**
   * Wrong codes tolerated on the disable endpoint before the cooldown starts.
   *
   * @since 1.1.0
   */
  public const int MAX_DISABLE_ATTEMPTS = 5;

  /**
   * How long the disable endpoint stays frozen once the attempts run out.
   *
   * Temporary on purpose. `confirmPending()` locks permanently because the
   * user recovers by restarting enrollment; disabling guards the ACTIVE
   * secret, so a permanent lock would leave them unable to turn TOTP off and
   * unable to re-enroll around it. The freeze converts an unbounded guessing
   * budget into roughly 480 attempts a day instead of 7 200, without ever
   * producing a state only support can undo.
   *
   * @since 1.1.0
   */
  public const string DISABLE_LOCK_DURATION = 'PT15M';
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the user ID (aggregate identity)
   * @param TotpSecret|null $activeSecret the confirmed, active secret
   * @param DateTimeImmutable|null $activeConfirmedAt when the active secret was confirmed
   * @param TotpSecret|null $pendingSecret the pending, unconfirmed secret
   * @param DateTimeImmutable|null $pendingCreatedAt when the pending secret was generated
   * @param int $attempts confirmation attempts made against the pending secret
   * @param int $maxAttempts maximum confirmation attempts allowed
   * @param DateTimeImmutable $createdAt creation time
   * @param DateTimeImmutable $updatedAt last update time
   */
  private function __construct(
    private readonly string $userId,
    private ?TotpSecret $activeSecret,
    private ?DateTimeImmutable $activeConfirmedAt,
    private ?TotpSecret $pendingSecret,
    private ?DateTimeImmutable $pendingCreatedAt,
    private int $attempts,
    private int $maxAttempts,
    private readonly DateTimeImmutable $createdAt,
    private DateTimeImmutable $updatedAt,
    private int $disableAttempts = 0,
    private ?DateTimeImmutable $disableLockedUntil = null,
  ) {
  }
  // #endregion

  // #region Named Constructors
  /**
   * Method startEnrollment.
   *
   * @static
   *
   * Starts a brand new enrollment with a pending secret.
   *
   * @since 1.0.0
   *
   * @param string $userId the user ID
   * @param TotpSecret $secret the newly generated pending secret
   * @param int $maxAttempts maximum confirmation attempts allowed
   *
   * @return self the new enrollment
   */
  public static function startEnrollment(string $userId, TotpSecret $secret, int $maxAttempts): self
  {
    $now = new DateTimeImmutable();

    return new self(
      userId: $userId,
      activeSecret: null,
      activeConfirmedAt: null,
      pendingSecret: $secret,
      pendingCreatedAt: $now,
      attempts: 0,
      maxAttempts: $maxAttempts,
      createdAt: $now,
      updatedAt: $now,
    );
  }

  /**
   * Method reconstitute.
   *
   * @static
   *
   * Reconstitutes an enrollment from persisted state.
   *
   * @since 1.0.0
   *
   * @param string $userId the user ID
   * @param TotpSecret|null $activeSecret the active secret
   * @param DateTimeImmutable|null $activeConfirmedAt active confirmation time
   * @param TotpSecret|null $pendingSecret the pending secret
   * @param DateTimeImmutable|null $pendingCreatedAt pending creation time
   * @param int $attempts confirmation attempts made
   * @param int $maxAttempts maximum confirmation attempts allowed
   * @param DateTimeImmutable $createdAt creation time
   * @param DateTimeImmutable $updatedAt last update time
   *
   * @return self the reconstituted enrollment
   */
  public static function reconstitute(
    string $userId,
    ?TotpSecret $activeSecret,
    ?DateTimeImmutable $activeConfirmedAt,
    ?TotpSecret $pendingSecret,
    ?DateTimeImmutable $pendingCreatedAt,
    int $attempts,
    int $maxAttempts,
    DateTimeImmutable $createdAt,
    DateTimeImmutable $updatedAt,
    int $disableAttempts = 0,
    ?DateTimeImmutable $disableLockedUntil = null,
  ): self {
    return new self(
      userId: $userId,
      activeSecret: $activeSecret,
      activeConfirmedAt: $activeConfirmedAt,
      pendingSecret: $pendingSecret,
      pendingCreatedAt: $pendingCreatedAt,
      attempts: $attempts,
      maxAttempts: $maxAttempts,
      createdAt: $createdAt,
      updatedAt: $updatedAt,
      disableAttempts: $disableAttempts,
      disableLockedUntil: $disableLockedUntil,
    );
  }
  // #endregion

  // #region Accessors
  public function userId(): string
  {
    return $this->userId;
  }

  public function activeSecret(): ?TotpSecret
  {
    return $this->activeSecret;
  }

  public function activeConfirmedAt(): ?DateTimeImmutable
  {
    return $this->activeConfirmedAt;
  }

  public function pendingSecret(): ?TotpSecret
  {
    return $this->pendingSecret;
  }

  public function pendingCreatedAt(): ?DateTimeImmutable
  {
    return $this->pendingCreatedAt;
  }

  public function disableAttempts(): int
  {
    return $this->disableAttempts;
  }

  public function disableLockedUntil(): ?DateTimeImmutable
  {
    return $this->disableLockedUntil;
  }

  public function attempts(): int
  {
    return $this->attempts;
  }

  public function maxAttempts(): int
  {
    return $this->maxAttempts;
  }

  /**
   * Method attemptsRemaining.
   *
   * Returns the remaining pending confirmation attempts.
   *
   * @since 1.0.0
   *
   * @return int the remaining attempt count
   */
  public function attemptsRemaining(): int
  {
    return max(0, $this->maxAttempts - $this->attempts);
  }

  public function createdAt(): DateTimeImmutable
  {
    return $this->createdAt;
  }

  public function updatedAt(): DateTimeImmutable
  {
    return $this->updatedAt;
  }

  /**
   * Method isActive.
   *
   * Whether the user currently has a confirmed, usable TOTP secret.
   *
   * @since 1.0.0
   *
   * @return bool true when active
   */
  public function isActive(): bool
  {
    return null !== $this->activeSecret;
  }

  /**
   * Method hasPending.
   *
   * Whether an unconfirmed secret is awaiting confirmation.
   *
   * @since 1.0.0
   *
   * @return bool true when a pending secret exists
   */
  public function hasPending(): bool
  {
    return null !== $this->pendingSecret;
  }
  // #endregion

  // #region Methods
  /**
   * Method requestNewSecret.
   *
   * Replaces the pending secret with a freshly generated one, resetting the
   * confirmation attempt counter. The active secret (if any) is left
   * untouched until the new pending secret is confirmed.
   *
   * @since 1.0.0
   *
   * @param TotpSecret $secret the newly generated pending secret
   * @param int $maxAttempts maximum confirmation attempts allowed
   *
   * @return void no return value
   */
  public function requestNewSecret(TotpSecret $secret, int $maxAttempts): void
  {
    $this->pendingSecret = $secret;
    $this->pendingCreatedAt = new DateTimeImmutable();
    $this->attempts = 0;
    $this->maxAttempts = $maxAttempts;
    $this->updatedAt = new DateTimeImmutable();
  }

  /**
   * Method confirmPending.
   *
   * Attempts to confirm the pending secret. Always consumes one attempt
   * (whether the code was valid or not); on success the pending secret
   * becomes the active secret.
   *
   * @since 1.0.0
   *
   * @param bool $codeValid whether the submitted code matched the pending secret
   *
   * @throws TotpEnrollmentNoPendingSecretException if there is no pending secret
   * @throws TotpEnrollmentMaxAttemptsException if attempts are exhausted
   *
   * @return bool true if confirmation succeeded
   */
  public function confirmPending(bool $codeValid): bool
  {
    if (null === $this->pendingSecret) {
      throw TotpEnrollmentNoPendingSecretException::forUser($this->userId);
    }

    if ($this->attempts >= $this->maxAttempts) {
      throw TotpEnrollmentMaxAttemptsException::forUser($this->userId);
    }

    ++$this->attempts;
    $this->updatedAt = new DateTimeImmutable();

    if (!$codeValid) {
      return false;
    }

    $this->activeSecret = $this->pendingSecret;
    $this->activeConfirmedAt = new DateTimeImmutable();
    $this->pendingSecret = null;
    $this->pendingCreatedAt = null;
    $this->attempts = 0;

    return true;
  }

  /**
   * Method disable.
   *
   * Disables TOTP by clearing the active (and any pending) secret, provided
   * the submitted code matched the current active secret.
   *
   * @since 1.0.0
   *
   * Wrong codes are counted, and once {@see self::MAX_DISABLE_ATTEMPTS} of
   * them land the endpoint freezes for {@see self::DISABLE_LOCK_DURATION}. The
   * freeze refuses the correct code too — otherwise it would not slow down the
   * one caller it exists for, the one who eventually guesses right — and it
   * lifts on its own, because a permanent lock on the ACTIVE secret would leave
   * the user unable to disable TOTP and unable to re-enroll around it.
   * @since 1.1.0
   *
   * @param bool $codeValid whether the submitted code matched the active secret
   * @param DateTimeImmutable|null $now the current instant, injectable for tests
   *
   * @throws TotpEnrollmentNotActiveException if there is no active secret
   * @throws TotpDisableTemporarilyLockedException if the cooldown has not elapsed
   *
   * @return bool true if disabling succeeded
   */
  public function disable(bool $codeValid, ?DateTimeImmutable $now = null): bool
  {
    if (null === $this->activeSecret) {
      throw TotpEnrollmentNotActiveException::forUser($this->userId);
    }

    $now ??= new DateTimeImmutable();

    if (null !== $this->disableLockedUntil) {
      if ($this->disableLockedUntil > $now) {
        throw TotpDisableTemporarilyLockedException::until($this->disableLockedUntil, $now);
      }

      // Cooldown elapsed: the slate is wiped so the next window starts whole.
      $this->disableLockedUntil = null;
      $this->disableAttempts = 0;
    }

    $this->updatedAt = $now;

    if (!$codeValid) {
      ++$this->disableAttempts;

      if ($this->disableAttempts >= self::MAX_DISABLE_ATTEMPTS) {
        $this->disableLockedUntil = $now->add(new DateInterval(self::DISABLE_LOCK_DURATION));
        $this->disableAttempts = 0;
      }

      return false;
    }

    $this->activeSecret = null;
    $this->activeConfirmedAt = null;
    $this->pendingSecret = null;
    $this->pendingCreatedAt = null;
    $this->attempts = 0;
    $this->disableAttempts = 0;
    $this->disableLockedUntil = null;

    return true;
  }
  // #endregion
}
