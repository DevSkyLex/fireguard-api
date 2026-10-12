<?php

declare(strict_types=1);

namespace Otp\Application\Contract\Challenge;

use DateTimeImmutable;

/**
 * Immutable current owner facts for a signed challenge receipt.
 *
 * @category Contract
 */
final readonly class ChallengeReplayProof
{
  /**
   * @param string $challengeId challenge identity, never its secret proof
   * @param string $userId owning account
   * @param OtpPurpose $purpose exact owner purpose
   * @param DateTimeImmutable $expiresAt original challenge deadline
   * @param DateTimeImmutable|null $verifiedAt one-time consumption time
   * @param int $attempts persisted failed-attempt count
   */
  public function __construct(public string $challengeId, public string $userId, public OtpPurpose $purpose, public DateTimeImmutable $expiresAt, public ?DateTimeImmutable $verifiedAt, public int $attempts)
  {
  }
}
