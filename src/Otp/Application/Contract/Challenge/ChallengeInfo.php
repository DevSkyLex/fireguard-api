<?php

declare(strict_types=1);

namespace Otp\Application\Contract\Challenge;

use DateTimeImmutable;

/**
 * Contract ChallengeInfo.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ChallengeInfo
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @param string $challengeToken the challenge token
   * @param string $maskedRecipient the masked recipient
   * @param DateTimeImmutable $expiresAt expiration timestamp
   * @param int $maxAttempts max verification attempts
   */
  public function __construct(
    public string $challengeToken,
    public string $maskedRecipient,
    public DateTimeImmutable $expiresAt,
    public int $maxAttempts,
  ) {
  }
  // #endregion
}
