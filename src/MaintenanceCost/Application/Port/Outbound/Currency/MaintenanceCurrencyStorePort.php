<?php

declare(strict_types=1);

namespace MaintenanceCost\Application\Port\Outbound\Currency;

use MaintenanceCost\Application\Contract\Currency\MaintenanceCurrencySnapshot;

/** Persistence boundary shared with organization-currency freezing. */
interface MaintenanceCurrencyStorePort
{
  /**
   * @template T
   *
   * @param callable():T $work
   *
   * @return T
   */
  public function synchronized(string $organizationId, callable $work): mixed;

  public function read(string $organizationId): MaintenanceCurrencySnapshot;

  public function save(MaintenanceCurrencySnapshot $currency): void;
}
