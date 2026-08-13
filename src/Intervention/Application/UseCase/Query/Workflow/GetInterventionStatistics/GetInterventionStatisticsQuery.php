<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Query\Workflow\GetInterventionStatistics;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase GetInterventionStatisticsQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetInterventionStatisticsQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the requesting user id value
   * @param string $organizationId the organization id value
   */
  public function __construct(
    public string $userId,
    public string $organizationId,
  ) {
  }
  // #endregion
}
