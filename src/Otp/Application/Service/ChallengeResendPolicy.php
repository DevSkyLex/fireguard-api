<?php

declare(strict_types=1);

namespace Otp\Application\Service;

use DateTimeImmutable;

use function max;

/**
 * Service ChallengeResendPolicy.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ChallengeResendPolicy
{
  // #region Constants
  /**
   * Constant RESEND_COOLDOWN_SECONDS.
   *
   * Resend cooldown in seconds.
   *
   * @since 1.0.0
   *
   * @var int
   */
  public const int RESEND_COOLDOWN_SECONDS = 60;
  // #endregion

  // #region Methods
  /**
   * Computes how many seconds remain before resend is allowed.
   *
   * @param DateTimeImmutable $createdAt challenge creation time
   * @param DateTimeImmutable $now current time
   *
   * @return int seconds until resend is allowed
   */
  public static function canResendIn(DateTimeImmutable $createdAt, DateTimeImmutable $now): int
  {
    $elapsed = $now->getTimestamp() - $createdAt->getTimestamp();

    return max(0, self::RESEND_COOLDOWN_SECONDS - $elapsed);
  }
  // #endregion
}
