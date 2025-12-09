<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\MfaChallenge;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * Result MfaChallengeResult
 * @final
 *
 * Result of MFA challenge generation.
 *
 * @category Result
 * @package Auth\Application\UseCase\Command\MfaChallenge
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MfaChallengeResult implements ResultMessage
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the MfaChallengeResult class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $challengeToken The challenge token for verification.
   * @param string $maskedRecipient The masked recipient for display.
   * @param DateTimeImmutable $expiresAt When the challenge expires.
   * @param int $maxAttempts Maximum verification attempts allowed.
   */
  public function __construct(
    public string $challengeToken,
    public string $maskedRecipient,
    public DateTimeImmutable $expiresAt,
    public int $maxAttempts,
  ) {
  }
  //#endregion
}
