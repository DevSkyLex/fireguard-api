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
   * @param array<string, mixed> $overview aggregated dashboard KPI summaries
   * @param array<string, float> $health computed dashboard rates
   * @param list<array{code: string, severity: string, count: int}> $alerts dashboard alerts
   * @param array<string, mixed> $comparison previous-period comparison metadata and deltas
   * @param array{
   *   facilities: list<array{bucket: string, value: int}>,
   *   members: list<array{bucket: string, value: int}>,
   *   equipment: list<array{bucket: string, value: int}>,
   *   inspections: list<array{bucket: string, value: int}>,
   * } $trends per-KPI running-total sparkline series, one point per day
   *           bucket across the dashboard period. Approximate for
   *           historical windows: each series is anchored on the current
   *           KPI total and walked backward using per-day creation
   *           counts, which is exact only when the period ends near
   *           "now".
   * @param list<array{
   *   id: string,
   *   number: int,
   *   name: string,
   *   status: string,
   *   priority: string,
   *   siteId: ?string,
   *   siteName: ?string,
   *   responsibleId: ?string,
   *   responsibleUserId: ?string,
   *   dueAt: ?string,
   *   updatedAt: string,
   * }> $recentInterventions the 5 most recently updated organization
   *                         interventions across every status, or an
   *                         empty list when the caller lacks
   *                         `organization.interventions.read`
   */
  public function __construct(
    public string $generatedAt,
    public array $period,
    public array $overview,
    public array $health,
    public array $alerts,
    public array $comparison,
    public array $trends,
    public array $recentInterventions,
  ) {
  }
}
