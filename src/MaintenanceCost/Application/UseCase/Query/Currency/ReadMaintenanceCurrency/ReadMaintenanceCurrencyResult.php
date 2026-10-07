<?php

declare(strict_types=1);

namespace MaintenanceCost\Application\UseCase\Query\Currency\ReadMaintenanceCurrency;

use MaintenanceCost\Application\Contract\Currency\MaintenanceCurrencySnapshot;
use Shared\Application\Message\ResultMessage;

/** Read-only organization currency, including the capture lock state. */
final readonly class ReadMaintenanceCurrencyResult implements ResultMessage
{
  public function __construct(public MaintenanceCurrencySnapshot $currency)
  {
  }
}
