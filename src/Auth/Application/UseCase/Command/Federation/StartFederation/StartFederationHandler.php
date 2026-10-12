<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\Federation\StartFederation;

use Auth\Application\Port\Inbound\Federation\FederationFlowPort;
use Shared\Application\Message\CommandHandler;

/**
 * StartFederationHandler.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class StartFederationHandler implements CommandHandler
{
  public function __construct(private FederationFlowPort $flows)
  {
  }

  public function __invoke(StartFederationCommand $command): StartFederationResult
  {
    $url = null === $command->userId
      ? $this->flows->startLogin($command->provider, $command->returnUrl, $command->browserBinding)
      : $this->flows->startLink($command->provider, $command->userId, $command->browserBinding);

    return new StartFederationResult($url, $command->browserBindingExpiresAt);
  }
}
