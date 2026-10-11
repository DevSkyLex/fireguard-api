<?php

declare(strict_types=1);

namespace Otp\Application\Port\Inbound\Challenge;

use DateTimeImmutable;
use Otp\Application\Contract\Challenge\ChallengeState;

/**
 * Reads challenge state and publishes its resend policy.
 *
 * @category Port
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface ChallengeAccessPort
{
  public const int RESEND_COOLDOWN_SECONDS = 60;

  /**
   * @param string $token the public challenge token
   */
  public function find(string $token): ?ChallengeState;

  /**
   * @param DateTimeImmutable $createdAt the challenge creation time
   */
  public function canResendIn(DateTimeImmutable $createdAt, DateTimeImmutable $now): int;
}
