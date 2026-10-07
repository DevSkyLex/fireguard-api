<?php

declare(strict_types=1);

namespace MaintenanceCost\Application\UseCase\Command\Currency\ConfigureMaintenanceCurrency;

use MaintenanceCost\Application\Contract\Currency\MaintenanceCurrencySnapshot;
use Shared\Application\Message\ResultMessage;

/** Canonical currency configuration. */
final readonly class ConfigureMaintenanceCurrencyResult implements ResultMessage
{
  public function __construct(public MaintenanceCurrencySnapshot $currency)
  {
  }
}
