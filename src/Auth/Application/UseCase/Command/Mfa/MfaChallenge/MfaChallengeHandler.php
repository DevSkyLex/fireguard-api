<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\Mfa\MfaChallenge;

use Auth\Application\Port\Outbound\Mfa\ChallengeGeneratorPort;
use Shared\Application\Message\CommandHandler;

/**
 * Handler MfaChallengeHandler.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MfaChallengeHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes the handler.
   *
   * @since 1.0.0
   *
   * @param ChallengeGeneratorPort $challengeGenerator the challenge generator
   */
  public function __construct(
    private readonly ChallengeGeneratorPort $challengeGenerator,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Handles MFA challenge generation.
   *
   * @since 1.0.0
   *
   * @param MfaChallengeCommand $command the command
   *
   * @return MfaChallengeResult the result
   */
  public function __invoke(MfaChallengeCommand $command): MfaChallengeResult
  {
    return $this->challengeGenerator->generate($command);
  }
  // #endregion
}
