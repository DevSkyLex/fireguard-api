<?php

declare(strict_types=1);

namespace Otp\Application\UseCase\Command\Challenge\VerifyMfaChallenge;

use Otp\Application\Contract\Challenge\OtpPurpose;
use Shared\Application\Message\CommandMessage;

/**
 * Class VerifyMfaChallengeCommand
 *
 * Binds a submitted MFA method and proof to signed login challenge claims.
 *
 * @category Command
 */
final readonly class VerifyMfaChallengeCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param string $challengeToken signed challenge token
   * @param string $userId signed account identifier
   * @param OtpPurpose $purpose expected LOGIN purpose
   * @param string $method otp or recovery_code
   * @param string $proof submitted proof
   *
   * @return void
   */
  public function __construct(public string $challengeToken, public string $userId, public OtpPurpose $purpose, public string $method, public string $proof)
  {
  }
  // #endregion
}
