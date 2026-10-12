<?php

declare(strict_types=1);

namespace Otp\Application\Service;

use Otp\Application\Contract\Challenge\{OtpPurpose, VerificationInfo};
use Otp\Application\Port\Inbound\Challenge\MfaChallengeVerificationPort;
use Otp\Application\UseCase\Command\Challenge\VerifyMfaChallenge\{VerifyMfaChallengeCommand, VerifyMfaChallengeHandler};

/**
 * Class MfaChallengeVerificationService
 *
 * Publishes the owner-bound transactional MFA use case to Auth.
 *
 * @category Service
 */
final readonly class MfaChallengeVerificationService implements MfaChallengeVerificationPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param VerifyMfaChallengeHandler $handler owning MFA verification use case
   *
   * @return void
   */
  public function __construct(private VerifyMfaChallengeHandler $handler)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method verifyFor
   * {@inheritDoc}
   */
  public function verifyFor(string $challengeToken, string $userId, OtpPurpose $purpose, string $method, string $proof): VerificationInfo
  {
    return ($this->handler)(new VerifyMfaChallengeCommand($challengeToken, $userId, $purpose, $method, $proof))->verification;
  }
  // #endregion
}
