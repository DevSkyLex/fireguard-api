<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\Federation\StartFederation;

use Auth\Application\Contract\Federation\FederatedProvider;
use Shared\Application\Message\CommandMessage;

/**
 * StartFederationCommand.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class StartFederationCommand implements CommandMessage
{
  public function __construct(public FederatedProvider $provider, public string $browserBinding, public string $returnUrl = '/account/security', public ?string $userId = null)
  {
  }
}
