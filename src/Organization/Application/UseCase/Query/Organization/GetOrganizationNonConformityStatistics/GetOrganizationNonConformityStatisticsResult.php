<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\GetOrganizationNonConformityStatistics;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase GetOrganizationNonConformityStatisticsResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOrganizationNonConformityStatisticsResult implements ResultMessage
{
  public function __construct(
    public int $totalCount,
    public int $openCount,
    public int $inProgressCount,
    public int $doneCount,
    public int $waivedCount,
    public int $lowSeverityCount,
    public int $mediumSeverityCount,
    public int $highSeverityCount,
    public int $criticalSeverityCount,
  ) {
  }
}
