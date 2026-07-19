<?php

declare(strict_types=1);

namespace Otp\Domain\Model\Totp;

use DateTimeImmutable;
use Otp\Domain\Exception\{
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
   * @param bool $codeValid whether the submitted code matched the active secret
   *
   * @throws TotpEnrollmentNotActiveException if there is no active secret
   *
   * @return bool true if disabling succeeded
   */
  public function disable(bool $codeValid): bool
  {
    if (null === $this->activeSecret) {
      throw TotpEnrollmentNotActiveException::forUser($this->userId);
    }

    if (!$codeValid) {
      return false;
    }

    $this->activeSecret = null;
    $this->activeConfirmedAt = null;
    $this->pendingSecret = null;
    $this->pendingCreatedAt = null;
    $this->attempts = 0;
    $this->updatedAt = new DateTimeImmutable();

    return true;
  }
  // #endregion
}
