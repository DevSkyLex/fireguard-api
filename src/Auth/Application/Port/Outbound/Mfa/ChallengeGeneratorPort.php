<?php

declare(strict_types=1);

namespace Auth\Application\Port\Outbound\Mfa;

use Auth\Application\UseCase\Command\MfaChallenge\MfaChallengeCommand;
use Auth\Application\UseCase\Command\MfaChallenge\MfaChallengeResult;

/**
 * Interface ChallengeGeneratorPort
 *
 * Port for generating MFA challenges.
 * This is an outbound port that the Auth module uses to request
 * challenge generation from an external system (e.g., OTP module).
 *
 * @category Port
 * @package Auth\Application\Port\Outbound\Mfa
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface ChallengeGeneratorPort
{
  /**
   * Method generate
   *
   * Generates an MFA challenge for the given parameters.
   *
   * @access public
   * @since 1.0.0
   *
   * @param MfaChallengeCommand $command The challenge generation parameters.
   *
   * @return MfaChallengeResult The generated challenge result.
   */
  public function generate(MfaChallengeCommand $command): MfaChallengeResult;
}
