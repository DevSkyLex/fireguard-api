<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Query\Federation\ListFederatedConnections;

use Auth\Application\Port\Inbound\Federation\FederationFlowPort;
use Shared\Application\Message\QueryHandler;

/**
 * ListFederatedConnectionsHandler.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListFederatedConnectionsHandler implements QueryHandler
{
  public function __construct(private FederationFlowPort $flows)
  {
  }

  public function __invoke(ListFederatedConnectionsQuery $query): ListFederatedConnectionsResult
  {
    return new ListFederatedConnectionsResult($this->flows->connections($query->userId));
  }
}
