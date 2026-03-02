<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\GetOrganizationStatistics;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase GetOrganizationStatisticsQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOrganizationStatisticsQuery implements QueryMessage
{
  // #region Constructor
  public function __construct(
    public string $organizationId,
  ) {
  }
  // #endregion
}
