<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\Federation\CompleteFederation;

use Auth\Application\Contract\Federation\{FederatedLoginContext, ProviderId};
use Shared\Application\Message\CommandMessage;

/**
 * CompleteFederationCommand.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CompleteFederationCommand implements CommandMessage
{
  public function __construct(public ProviderId $provider, public string $state, public string $code, public string $browserBinding, public ?string $providerError, public FederatedLoginContext $loginContext, public ?string $userId = null)
  {
  }
}
