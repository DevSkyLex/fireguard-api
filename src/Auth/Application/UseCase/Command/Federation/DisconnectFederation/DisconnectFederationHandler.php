<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\Federation\DisconnectFederation;

use Auth\Application\Port\Inbound\Federation\FederationFlowPort;
use Shared\Application\Message\CommandHandler;

/**
 * DisconnectFederationHandler.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DisconnectFederationHandler implements CommandHandler
{
  public function __construct(private FederationFlowPort $flows)
  {
  }

  public function __invoke(DisconnectFederationCommand $command): DisconnectFederationResult
  {
    return new DisconnectFederationResult($this->flows->remove($command->provider, $command->userId));
  }
}
