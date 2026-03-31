<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Dto\Output\Organization;

use ApiPlatform\Metadata\ApiProperty;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * API output returned by the aggregate organization dashboard endpoint.
 *
 * The payload is front-oriented but presentation-agnostic: stable keys, values,
 * iterable collections, and no UI wording meant to replace frontend pipes.
 */
final class OrganizationDashboardOutput
{
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $generatedAt = '';

  /**
   * @var array{from?: string, to?: string, granularity?: string, comparison?: string, timezone?: string}
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public array $period = [];

  /**
   * @var array<string, array{summary: list<array{key: string, value: int}>, breakdowns: list<array{key: string, items: list<array{key: string, value: int}>}>}>
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public array $overview = [];

  /**
   * @var array{metrics: list<array{key: string, value: float, unit: string}>}
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public array $health = ['metrics' => []];

  /**
   * @var list<array{code: string, severity: string, count: int}>
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public array $alerts = [];

  /**
   * @var list<array{metric: string, summary: array{total: int, unit: string}, series: list<array{bucket: string, value: int}>, comparison: array{mode: string, current: ?int, previous: ?int, delta: ?float}}>
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public array $trendMetrics = [];

  /**
   * @var array{mode: string, from: ?string, to: ?string, metrics: list<array{metric: string, current: ?int, previous: ?int, delta: ?float}>, health: array{metrics: list<array{key: string, unit: string, current: ?float, previous: ?float, delta: ?float}>}}
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public array $comparison = ['mode' => 'none', 'from' => null, 'to' => null, 'metrics' => [], 'health' => ['metrics' => []]];
}
