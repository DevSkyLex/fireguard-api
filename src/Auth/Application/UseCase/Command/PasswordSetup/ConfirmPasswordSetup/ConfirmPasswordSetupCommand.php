<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\PasswordSetup\ConfirmPasswordSetup;

use Shared\Application\Message\CommandMessage;

/**
 * ConfirmPasswordSetupCommand.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ConfirmPasswordSetupCommand implements CommandMessage
{
  public function __construct(public string $userId, public string $token, public string $code, public string $password)
  {
  }
}
