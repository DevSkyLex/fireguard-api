<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\GetOrganizationInspectionStatistics;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase GetOrganizationInspectionStatisticsResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOrganizationInspectionStatisticsResult implements ResultMessage
{
  /**
   * @param array<string, int> $countsByInspectorType map of inspector type => count
   */
  public function __construct(
    public int $totalCount,
    public int $draftCount,
    public int $submittedCount,
    public int $closedCount,
    public int $passCount,
    public int $failCount,
    public int $partialCount,
    public array $countsByInspectorType,
    public int $performedLast7DaysCount,
    public int $performedLast30DaysCount,
  ) {
  }
}
