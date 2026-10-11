<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Query\Federation\ListFederatedProviders;

use Auth\Application\Port\Inbound\Federation\FederationFlowPort;
use Shared\Application\Message\QueryHandler;

/**
 * ListFederatedProvidersHandler.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListFederatedProvidersHandler implements QueryHandler
{
  public function __construct(private FederationFlowPort $flows)
  {
  }

  public function __invoke(ListFederatedProvidersQuery $query): ListFederatedProvidersResult
  {
    return new ListFederatedProvidersResult($this->flows->enabledProviders());
  }
}
