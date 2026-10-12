<?php

declare(strict_types=1);

namespace Otp\Application\Contract\Challenge;

use DateTimeImmutable;

/**
 * Fresh challenge ownership and consumption facts without its code or digest.
 *
 * @category Contract
 */
final readonly class ChallengeLifecycleProof
{
  /**
   * @param string $challengeId owning challenge
   * @param string $userId owning account
   * @param OtpPurpose $purpose exact challenge capability
   * @param string $recipient bound destination
   * @param string $channel owner delivery channel
   * @param string $status current challenge state
   * @param DateTimeImmutable $expiresAt original validity deadline
   * @param ?DateTimeImmutable $verifiedAt committed consumption timestamp
   * @param int $attempts durable failure count
   */
  public function __construct(public string $challengeId, public string $userId, public OtpPurpose $purpose, public string $recipient, public string $channel, public string $status, public DateTimeImmutable $expiresAt, public ?DateTimeImmutable $verifiedAt, public int $attempts)
  {
  }
}
