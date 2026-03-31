<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Dto\Output\Organization;

use ApiPlatform\Metadata\ApiProperty;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * API output returned by the aggregate organization dashboard endpoint.
 *
 * The payload is front-oriented: normalized widgets, alert copy ready for display,
 * iterable health metrics, iterable comparison metrics, and a chart catalog under
 * `trendMetrics`.
 */
final class OrganizationDashboardOutput
{
  /**
   * ISO 8601 datetime at which snapshot metrics and period aggregations were generated.
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $generatedAt = '';

  /**
   * Requested dashboard period metadata.
   *
   * Keys:
   * - `from`: ISO 8601 inclusive lower bound
   * - `to`: ISO 8601 inclusive upper bound
   * - `granularity`: effective bucket granularity (`day`, `week`, `month`)
   * - `comparison`: comparison mode (`none`, `previous_period`)
   * - `timezone`: IANA timezone used for rendering and bucket boundaries
   *
   * @var array{from?: string, to?: string, granularity?: string, comparison?: string, timezone?: string}
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public array $period = [];

  /**
   * Snapshot KPI widgets grouped by domain (`members`, `facilities`, `equipment`, etc.).
   *
   * Each widget exposes a stable key, a display label, a normalized scalar summary,
   * and zero or more normalized breakdowns.
   *
   * @var array<string, array{key: string, label: string, summary: list<array{key: string, label: string, value: int}>, breakdowns: list<array{key: string, label: string, items: list<array{key: string, label: string, value: int}>}>}>
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public array $overview = [];

  /**
   * Global health KPIs expressed as percentages.
   *
   * @var array{metrics: list<array{key: string, label: string, value: float, unit: string}>}
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public array $health = ['metrics' => []];

  /**
   * Active dashboard alerts derived from current snapshot metrics.
   *
   * @var list<array{code: string, severity: string, count: int, label: string, description: string}>
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public array $alerts = [];

  /**
   * Front-oriented catalog of dashboard trend metrics.
   *
   * @var list<array{metric: string, label: string, description: string, summary: array{total: int, unit: string}, series: list<array{bucket: string, value: int}>, comparison: array{mode: string, current: ?int, previous: ?int, delta: ?float}}>
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public array $trendMetrics = [];

  /**
   * Optional previous-period comparison block for trend metrics and period-scoped health rates.
   *
   * @var array{mode: string, from: ?string, to: ?string, metrics: list<array{metric: string, label: string, description: string, current: ?int, previous: ?int, delta: ?float}>, health: array{metrics: list<array{key: string, label: string, unit: string, current: ?float, previous: ?float, delta: ?float}>}}
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public array $comparison = ['mode' => 'none', 'from' => null, 'to' => null, 'metrics' => [], 'health' => ['metrics' => []]];
}
