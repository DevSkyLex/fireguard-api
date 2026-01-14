<?php

declare(strict_types=1);

namespace Otp\Application\UseCase\Command\Challenge\ResendChallenge;

use Shared\Application\Message\CommandMessage;

/**
 * Command ResendChallengeCommand.
 *
 * @category Command
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ResendChallengeCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @param string $challengeToken the challenge token
   * @param string $userId the user identifier
   */
  public function __construct(
    public string $challengeToken,
    public string $userId,
  ) {
  }
  // #endregion
}
