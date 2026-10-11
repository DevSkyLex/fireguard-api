<?php

declare(strict_types=1);

namespace Otp\Application\Contract\Challenge;

use DateTimeImmutable;

/**
 * Immutable challenge state exposed to authenticated orchestration.
 *
 * @category Contract
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ChallengeState
{
  /**
   * @param string $userId the challenge owner
   * @param OtpPurpose $purpose the challenge purpose
   * @param OtpChannel $channel the delivery channel
   * @param string $recipient the bound recipient
   * @param string $status the challenge state
   * @param DateTimeImmutable $createdAt the creation time
   */
  public function __construct(
    public string $userId,
    public OtpPurpose $purpose,
    public OtpChannel $channel,
    public string $recipient,
    public string $status,
    public DateTimeImmutable $createdAt,
  ) {
  }
}
