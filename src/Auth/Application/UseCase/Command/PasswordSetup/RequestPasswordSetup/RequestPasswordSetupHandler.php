<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\PasswordSetup\RequestPasswordSetup;

use Auth\Application\Port\Inbound\Federation\PasswordSetupPort;
use Shared\Application\Message\CommandHandler;

/**
 * RequestPasswordSetupHandler.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RequestPasswordSetupHandler implements CommandHandler
{
  public function __construct(private PasswordSetupPort $passwords)
  {
  }

  public function __invoke(RequestPasswordSetupCommand $command): RequestPasswordSetupResult
  {
    return new RequestPasswordSetupResult($this->passwords->request($command->userId));
  }
}
