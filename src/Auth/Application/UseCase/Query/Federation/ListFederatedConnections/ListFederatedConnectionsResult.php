<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Query\Federation\ListFederatedConnections;

use Auth\Application\Contract\Federation\FederatedConnections;
use Shared\Application\Message\ResultMessage;

/**
 * ListFederatedConnectionsResult.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListFederatedConnectionsResult implements ResultMessage
{
  public function __construct(public FederatedConnections $connections)
  {
  }
}
