<?php

declare(strict_types=1);

namespace Shared\Application\UseCase\Query\Health;

use Shared\Application\Message\ResultMessage;

use function date;

/**
 * Result HealthCheckResult.
 *
 * Contains the health check status of the application.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class HealthCheckResult implements ResultMessage
{
  // #region Constants
  public const string STATUS_HEALTHY = 'healthy';

  public const string STATUS_DEGRADED = 'degraded';

  public const string STATUS_UNHEALTHY = 'unhealthy';
  // #endregion

  // #region Constructor
  /**
   * @param string $status Overall health status
   * @param bool $database Database connectivity status
   * @param bool $cache Cache connectivity status
   * @param string $version Application version
   * @param string $timestamp Current timestamp
   */
  public function __construct(
    public string $status,
    public bool $database,
    public bool $cache,
    public string $version = '1.0.0',
    public string $timestamp = '',
  ) {
  }
  // #endregion

  // #region Factory Methods
  /**
   * Method healthy.
   *
   * Creates a healthy status result.
   *
   * @since 1.0.0
   *
   * @return self Healthy result
   */
  public static function healthy(): self
  {
    return new self(
      status: self::STATUS_HEALTHY,
      database: true,
      cache: true,
      timestamp: date('c'),
    );
  }

  /**
   * Method degraded.
   *
   * Creates a degraded status result (partial failure).
   *
   * @since 1.0.0
   *
   * @param bool $database Database status
   * @param bool $cache Cache status
   *
   * @return self Degraded result
   */
  public static function degraded(bool $database, bool $cache): self
  {
    return new self(
      status: self::STATUS_DEGRADED,
      database: $database,
      cache: $cache,
      timestamp: date('c'),
    );
  }

  /**
   * Method unhealthy.
   *
   * Creates an unhealthy status result (critical failure).
   *
   * @since 1.0.0
   *
   * @param bool $database Database status
   * @param bool $cache Cache status
   *
   * @return self Unhealthy result
   */
  public static function unhealthy(bool $database, bool $cache): self
  {
    return new self(
      status: self::STATUS_UNHEALTHY,
      database: $database,
      cache: $cache,
      timestamp: date('c'),
    );
  }
  // #endregion
}
