<?php

declare(strict_types=1);

namespace Otp\Application\Port\Inbound\Challenge;

use Otp\Application\Contract\Challenge\ChallengeLifecycleProof;

/**
 * Reads challenge ownership freshly for credential receipt authorization.
 *
 * @category Port
 */
interface ChallengeLifecycleProofPort
{
  /**
   * @param string $token original public challenge proof
   */
  public function current(string $token): ?ChallengeLifecycleProof;

  /**
   * @param string $challengeId signed owner challenge identifier
   */
  public function byId(string $challengeId): ?ChallengeLifecycleProof;
}
