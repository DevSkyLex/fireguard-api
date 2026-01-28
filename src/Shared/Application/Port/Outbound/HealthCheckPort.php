<?php

declare(strict_types=1);

namespace Shared\Application\Port\Outbound;

/**
 * Port HealthCheckPort.
 *
 * Defines the contract for health check operations
 * on external dependencies (database, cache, etc.).
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface HealthCheckPort
{
  /**
   * Method checkDatabase.
   *
   * Checks if the database connection is healthy.
   *
   * @since 1.0.0
   *
   * @return bool true if database is reachable, false otherwise
   */
  public function checkDatabase(): bool;

  /**
   * Method checkCache.
   *
   * Checks if the cache system is healthy.
   *
   * @since 1.0.0
   *
   * @return bool true if cache is reachable, false otherwise
   */
  public function checkCache(): bool;
}
