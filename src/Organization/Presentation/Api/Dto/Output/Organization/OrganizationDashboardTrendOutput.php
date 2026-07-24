<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Dto\Output\Organization;

use ApiPlatform\Metadata\ApiProperty;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * API output returned by dashboard trend endpoints.
 *
 * The payload is intentionally compact and only exposes metric identifiers,
 * values, series, and comparison data.
 */
final class OrganizationDashboardTrendOutput
{
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $generatedAt = '';

  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $metric = '';

  /**
   * @var array{from?: string, to?: string, granularity?: string, comparison?: string, timezone?: string}
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public array $period = [];

  /**
   * @var array<string, int|float|string>
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public array $summary = [];

  /**
   * @var list<array{bucket: string, value: int}>
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public array $series = [];

  /**
   * @var array{mode: string, from: ?string, to: ?string, summary: array{total?: int, delta?: float}, series: list<array{bucket: string, value: int}>}
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public array $comparison = ['mode' => 'none', 'from' => null, 'to' => null, 'summary' => [], 'series' => []];

  /**
   * Per-metric series, keyed by metric identifier, populated only when the
   * `metrics` filter requested more than the primary `metric` (e.g. the
   * two-series non-conformities opened/resolved chart). Every entry shares
   * this payload's `period`, so the frontend can render several series on one
   * chart without stitching independent calls together.
   *
   * @var array<string, list<array{bucket: string, value: int}>>
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public array $seriesByMetric = [];
}
