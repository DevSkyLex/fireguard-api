<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Query\Federation\ListFederatedConnections;

use Shared\Application\Message\QueryMessage;

/**
 * ListFederatedConnectionsQuery.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListFederatedConnectionsQuery implements QueryMessage
{
  public function __construct(public string $userId)
  {
  }
}
