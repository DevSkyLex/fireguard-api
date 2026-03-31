<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\GetOrganizationDashboard;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase GetOrganizationDashboardResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOrganizationDashboardResult implements ResultMessage
{
  /**
   * Constructor.
   *
   * @param string $generatedAt ISO 8601 generation datetime
   * @param array<string, string> $period dashboard period metadata
   * @param array<string, mixed> $overview aggregated dashboard counts
   * @param array<string, float> $health computed dashboard rates
   * @param list<array{code: string, severity: string, count: int}> $alerts dashboard alerts
   * @param array<string, list<array{bucket: string, value: int}>> $trends dashboard time series
   * @param array<string, mixed> $comparison previous-period comparison metadata and deltas
   */
  public function __construct(
    public string $generatedAt,
    public array $period,
    public array $overview,
    public array $health,
    public array $alerts,
    public array $trends,
    public array $comparison,
  ) {
  }
}
