<?php

declare(strict_types=1);

namespace MaintenanceCost\Application\Contract\Currency;

/** Organization currency retained once a financial fact has been captured. */
final readonly class MaintenanceCurrencySnapshot
{
  public function __construct(public string $organizationId, public string $currency, public bool $locked)
  {
  }
}
