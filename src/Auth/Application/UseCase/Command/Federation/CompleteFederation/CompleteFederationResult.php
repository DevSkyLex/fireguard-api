<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\Federation\CompleteFederation;

use Auth\Application\Contract\Federation\{FederatedConnections, FederatedLogin};
use Shared\Application\Message\ResultMessage;

/**
 * CompleteFederationResult.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CompleteFederationResult implements ResultMessage
{
  public function __construct(public ?FederatedLogin $login = null, public ?FederatedConnections $connections = null)
  {
  }
}
