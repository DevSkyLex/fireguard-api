<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\GetOrganizationInspectionStatistics;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase GetOrganizationInspectionStatisticsQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOrganizationInspectionStatisticsQuery implements QueryMessage
{
  public function __construct(
    public string $organizationId,
    public string $userId,
  ) {
  }
}
