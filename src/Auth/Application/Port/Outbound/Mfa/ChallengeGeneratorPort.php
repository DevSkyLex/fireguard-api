<?php

declare(strict_types=1);

namespace Auth\Application\Port\Outbound\Mfa;

use Auth\Application\UseCase\Command\Mfa\MfaChallenge\{MfaChallengeCommand, MfaChallengeResult};

/**
 * Interface ChallengeGeneratorPort.
 *
 * Port for generating MFA challenges.
 * This is an outbound port that the Auth module uses to request
 * challenge generation from an external system (e.g., OTP module).
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface ChallengeGeneratorPort
{
  /**
   * Method generate.
   *
   * Generates an MFA challenge for the given parameters.
   *
   * @since 1.0.0
   *
   * @param MfaChallengeCommand $command the challenge generation parameters
   *
   * @return MfaChallengeResult the generated challenge result
   */
  public function generate(MfaChallengeCommand $command): MfaChallengeResult;
}
