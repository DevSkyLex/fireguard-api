<?php

declare(strict_types=1);

namespace Shared\Application\UseCase\Query\Health;

use Shared\Application\Message\QueryMessage;

/**
 * Query HealthCheckQuery.
 *
 * Requests a health check of the application
 * and its dependencies.
 *
 * @category Query
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class HealthCheckQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Requests the current health status with optional dependency details.
   *
   * @access public
   *
   * @param bool $includeDetails whether the result should include individual dependency status
   *
   * @return void
   */
  public function __construct(
    public bool $includeDetails = false,
  ) {
  }
  // #endregion
}
