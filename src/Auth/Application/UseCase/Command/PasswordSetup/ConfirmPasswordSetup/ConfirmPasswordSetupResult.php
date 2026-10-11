<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\PasswordSetup\ConfirmPasswordSetup;

use Shared\Application\Message\ResultMessage;

/**
 * ConfirmPasswordSetupResult.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ConfirmPasswordSetupResult implements ResultMessage
{
  public function __construct(public bool $success, public int $attemptsRemaining = 0, public ?string $errorCode = null)
  {
  }
}
