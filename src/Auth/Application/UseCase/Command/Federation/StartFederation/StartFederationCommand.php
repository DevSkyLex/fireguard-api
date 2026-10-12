<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\Federation\StartFederation;

use Auth\Application\Contract\Federation\ProviderId;
use DateTimeImmutable;
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
  public function __construct(public ProviderId $provider, public string $browserBinding, public string $returnUrl = '/account/security', public ?string $userId = null, public ?DateTimeImmutable $browserBindingExpiresAt = null)
  {
  }
}
