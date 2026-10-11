<?php

declare(strict_types=1);

namespace Auth\Application\Port\Inbound\Federation;

use Otp\Application\Contract\Challenge\ChallengeInfo;

/**
 * PasswordSetupPort.
 *
 * @category Port
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface PasswordSetupPort
{
  public function request(string $userId): ChallengeInfo;

  /**
   * @return array{attemptsRemaining: int} confirmed challenge attempts
   */
  public function confirm(string $userId, string $token, string $code, string $password): array;
}
