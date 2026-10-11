<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\Federation\DisconnectFederation;

use Auth\Application\Contract\Federation\FederatedProvider;
use Shared\Application\Message\CommandMessage;

/**
 * DisconnectFederationCommand.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DisconnectFederationCommand implements CommandMessage
{
  public function __construct(public FederatedProvider $provider, public string $userId)
  {
  }
}
