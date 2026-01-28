<?php

declare(strict_types=1);

namespace Shared\Application\UseCase\Query\Health;

use Shared\Application\Message\QueryHandler;
use Shared\Application\Port\Outbound\HealthCheckPort;

/**
 * Handler HealthCheckHandler.
 *
 * Handles health check queries by verifying
 * the status of application dependencies.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class HealthCheckHandler implements QueryHandler
{
  // #region Constructor
  public function __construct(
    private HealthCheckPort $healthCheck,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Handles the health check query.
   *
   * @since 1.0.0
   *
   * @param HealthCheckQuery $query The health check query
   *
   * @return HealthCheckResult The health check result
   */
  public function __invoke(HealthCheckQuery $query): HealthCheckResult
  {
    $databaseHealthy = $this->healthCheck->checkDatabase();
    $cacheHealthy = $this->healthCheck->checkCache();

    // Database failure is critical - return unhealthy
    if (!$databaseHealthy) {
      return HealthCheckResult::unhealthy(
        database: false,
        cache: $cacheHealthy,
      );
    }

    // Database is healthy, check cache
    if (!$cacheHealthy) {
      // Cache failure is degraded, not critical
      return HealthCheckResult::degraded(
        database: true,
        cache: false,
      );
    }

    // Both database and cache are healthy
    return HealthCheckResult::healthy();
  }
  // #endregion
}
