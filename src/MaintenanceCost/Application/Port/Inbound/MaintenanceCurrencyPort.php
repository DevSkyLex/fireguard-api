<?php

declare(strict_types=1);

namespace MaintenanceCost\Application\Port\Inbound;

/** Currency access for financial fact owners on the main database. */
interface MaintenanceCurrencyPort
{
  /**
   * Reads the configured currency, defaulting to EUR without creating a row.
   */
  public function forOrganization(string $organizationId): string;

  /**
   * Atomically freezes the currency; requires the caller's active main transaction.
   */
  public function lock(string $organizationId): string;
}
