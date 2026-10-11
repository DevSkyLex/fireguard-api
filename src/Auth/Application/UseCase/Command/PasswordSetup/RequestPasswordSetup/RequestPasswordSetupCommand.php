<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\PasswordSetup\RequestPasswordSetup;

use Shared\Application\Message\CommandMessage;

/**
 * RequestPasswordSetupCommand.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RequestPasswordSetupCommand implements CommandMessage
{
  public function __construct(public string $userId)
  {
  }
}
