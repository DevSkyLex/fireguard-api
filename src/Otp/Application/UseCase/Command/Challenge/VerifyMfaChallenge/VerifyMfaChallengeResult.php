<?php

declare(strict_types=1);

namespace Otp\Application\UseCase\Command\Challenge\VerifyMfaChallenge;

use Otp\Application\Contract\Challenge\VerificationInfo;
use Shared\Application\Message\ResultMessage;

/**
 * Class VerifyMfaChallengeResult
 *
 * Carries committed challenge verification status without credentials.
 *
 * @category Result
 */
final readonly class VerifyMfaChallengeResult implements ResultMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param VerificationInfo $verification safe verification projection
   *
   * @return void
   */
  public function __construct(public VerificationInfo $verification)
  {
  }
  // #endregion
}
