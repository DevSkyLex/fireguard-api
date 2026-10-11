<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\Federation\DisconnectFederation;

use Auth\Application\Contract\Federation\FederatedConnections;
use Shared\Application\Message\ResultMessage;

/**
 * DisconnectFederationResult.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DisconnectFederationResult implements ResultMessage
{
  public function __construct(public FederatedConnections $connections)
  {
  }
}
