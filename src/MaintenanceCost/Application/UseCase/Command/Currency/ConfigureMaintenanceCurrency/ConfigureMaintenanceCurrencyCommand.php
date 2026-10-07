<?php

declare(strict_types=1);

namespace MaintenanceCost\Application\UseCase\Command\Currency\ConfigureMaintenanceCurrency;

use Shared\Application\Message\CommandMessage;

/** Explicit configuration request before financial facts lock the organization currency. */
final readonly class ConfigureMaintenanceCurrencyCommand implements CommandMessage
{
  public function __construct(public string $organizationId, public string $actorUserId, public string $currency)
  {
  }
}
