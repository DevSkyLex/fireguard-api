<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\PasswordSetup\RequestPasswordSetup;

use Otp\Application\Contract\Challenge\ChallengeInfo;
use Shared\Application\Message\ResultMessage;

/**
 * RequestPasswordSetupResult.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RequestPasswordSetupResult implements ResultMessage
{
  public function __construct(public ChallengeInfo $challenge)
  {
  }
}
