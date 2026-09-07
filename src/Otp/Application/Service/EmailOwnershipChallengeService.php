<?php

declare(strict_types=1);

namespace Otp\Application\Service;

use Otp\Application\Contract\Challenge\{OtpPurpose, VerificationInfo};
use Otp\Application\Port\Inbound\Challenge\{EmailOwnershipChallengePort, OtpChallengePort};
use Shared\Application\Port\Outbound\TransactionManagerPort;

/**
 * Service EmailOwnershipChallengeService.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class EmailOwnershipChallengeService implements EmailOwnershipChallengePort
{
  // #region Constructor
  /**
   * @since 1.0.0
   *
   * @param OtpChallengePort $challenges OTP verification capability
   * @param TransactionManagerPort $transaction explicitly bound auth transaction manager
   */
  public function __construct(private OtpChallengePort $challenges, private TransactionManagerPort $transaction)
  {
  }
  // #endregion

  // #region Methods
  /**
   * @since 1.0.0
   *
   * @param string $userId expected account
   * @param string $email current address
   * @param string $token challenge token
   * @param string $code submitted code
   *
   * @return VerificationInfo the committed verification result
   */
  public function verify(string $userId, string $email, string $token, string $code): VerificationInfo
  {
    return $this->transaction->transactional(fn (): VerificationInfo => $this->challenges->verifyFor(
      challengeToken: $token,
      code: $code,
      userId: $userId,
      purpose: OtpPurpose::EMAIL_OWNERSHIP,
      recipient: $email,
    ));
  }
  // #endregion
}
