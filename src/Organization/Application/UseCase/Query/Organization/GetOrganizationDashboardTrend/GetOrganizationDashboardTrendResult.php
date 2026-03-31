<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\GetOrganizationDashboardTrend;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase GetOrganizationDashboardTrendResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOrganizationDashboardTrendResult implements ResultMessage
{
  /**
   * Constructor.
   *
   * @param string $generatedAt ISO 8601 generation datetime
   * @param string $metric trend metric identifier
   * @param array<string, string> $period trend period metadata
   * @param array<string, int|float> $summary aggregated series summary
   * @param list<array{bucket: string, value: int}> $series normalized trend buckets
   * @param array<string, mixed> $comparison previous-period comparison metadata and series
   */
  public function __construct(
    public string $generatedAt,
    public string $metric,
    public array $period,
    public array $summary,
    public array $series,
    public array $comparison,
  ) {
  }
}
