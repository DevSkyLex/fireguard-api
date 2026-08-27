<?php

declare(strict_types=1);

namespace Otp\Domain\Exception;

use DateTimeImmutable;
use DomainException;

use function max;
use function sprintf;

/**
 * Exception TotpDisableTemporarilyLockedException.
 *
 * Raised when too many wrong codes have been submitted to the TOTP disable
 * endpoint and the cooldown has not elapsed.
 *
 * The lock is deliberately temporary. `confirmPending()` may lock permanently
 * because the user recovers by restarting enrollment; disabling guards the
 * ACTIVE secret, so a permanent lock would leave the user unable to turn TOTP
 * off AND unable to re-enroll around it — a dead end with no self-service exit.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class TotpDisableTemporarilyLockedException extends DomainException
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $message the exception message
   * @param int $retryAfterSeconds seconds remaining before another attempt is accepted
   */
  private function __construct(string $message, public readonly int $retryAfterSeconds)
  {
    parent::__construct($message);
  }
  // #endregion

  // #region Named Constructors
  /**
   * Method until.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param DateTimeImmutable $lockedUntil the instant the cooldown ends
   * @param DateTimeImmutable $now the current instant
   *
   * @return self the exception instance
   */
  public static function until(DateTimeImmutable $lockedUntil, DateTimeImmutable $now): self
  {
    $retryAfterSeconds = max(1, $lockedUntil->getTimestamp() - $now->getTimestamp());

    return new self(
      sprintf('Too many incorrect codes. Try again in %d second(s).', $retryAfterSeconds),
      $retryAfterSeconds,
    );
  }
  // #endregion
}
