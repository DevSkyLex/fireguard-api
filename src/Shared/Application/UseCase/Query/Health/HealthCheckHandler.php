<?php

declare(strict_types=1);

namespace Shared\Application\UseCase\Query\Health;

use Shared\Application\Message\QueryHandler;
use Shared\Application\Port\Outbound\{HealthCheckPort, ProductConfigPort};

/**
 * Handler HealthCheckHandler.
 *
 * Handles health check queries by verifying
 * the status of application dependencies.
 *
 * @category Handler
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class HealthCheckHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Collects dependency health through the shared health-check port.
   *
   * @access public
   *
   * @param HealthCheckPort $healthCheck port that probes configured dependencies
   * @param ProductConfigPort $product the deployment-owned application version
   *
   * @return void
   */
  public function __construct(
    private HealthCheckPort $healthCheck,
    private ProductConfigPort $product,
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
    $version = $this->product->identity()->version;

    // Database failure is critical - return unhealthy
    if (!$databaseHealthy) {
      return HealthCheckResult::unhealthy(
        database: false,
        cache: $cacheHealthy,
        version: $version,
      );
    }

    // Database is healthy, check cache
    if (!$cacheHealthy) {
      // Cache failure is degraded, not critical
      return HealthCheckResult::degraded(
        database: true,
        cache: false,
        version: $version,
      );
    }

    // Both database and cache are healthy
    return HealthCheckResult::healthy($version);
  }
  // #endregion
}
