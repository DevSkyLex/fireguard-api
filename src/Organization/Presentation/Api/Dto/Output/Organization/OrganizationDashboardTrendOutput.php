<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Dto\Output\Organization;

use ApiPlatform\Metadata\ApiProperty;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * API output returned by dashboard trend endpoints.
 *
 * The payload is intentionally compact and includes only canonical fields needed by the frontend.
 */
final class OrganizationDashboardTrendOutput
{
  /**
   * ISO 8601 datetime at which the trend payload was generated.
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $generatedAt = '';

  /**
   * Stable metric identifier returned by the endpoint.
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $metric = '';

  /**
   * Ready-to-render chart metadata.
   *
   * @var array{label: string, description: string}
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public array $display = ['label' => '', 'description' => ''];

  /**
   * Requested trend period metadata.
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
   * Aggregate summary for the requested metric.
   *
   * Common keys:
   * - `total`: total count over the requested period
   * - `unit`: semantic unit for rendering (`count`)
   *
   * @var array<string, int|float|string>
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public array $summary = [];

  /**
   * Primary chart-ready series for the requested metric.
   *
   * @var list<array{bucket: string, value: int}>
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public array $series = [];

  /**
   * Optional previous-period comparison payload.
   *
   * @var array{mode: string, from: ?string, to: ?string, summary: array{total?: int, delta?: float}, series: list<array{bucket: string, value: int}>}
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public array $comparison = ['mode' => 'none', 'from' => null, 'to' => null, 'summary' => [], 'series' => []];
}
