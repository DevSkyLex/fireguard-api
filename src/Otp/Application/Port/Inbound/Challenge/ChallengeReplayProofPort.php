<?php

declare(strict_types=1);

namespace Otp\Application\Port\Inbound\Challenge;

use Otp\Application\Contract\Challenge\{ChallengeReplayProof, OtpPurpose};

/**
 * Exposes fresh, exactly owner-bound challenge facts before decrypting a receipt.
 *
 * @category Inbound Port
 */
interface ChallengeReplayProofPort
{
  /**
   * @param string $token signed preauthentication challenge token
   * @param string $userId authenticated challenge owner
   * @param OtpPurpose $purpose signed intended purpose
   *
   * @return ChallengeReplayProof|null current owner facts
   */
  public function current(string $token, string $userId, OtpPurpose $purpose): ?ChallengeReplayProof;
}
