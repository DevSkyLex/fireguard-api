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
 * iterable collections, and fixed comparison-card labels for the default KPI set.
 */
final class OrganizationDashboardOutput
{
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $generatedAt = '';

  /**
   * @var array{from?: string, to?: string, comparison?: string, timezone?: string}
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public array $period = [];

  /**
   * @var array<string, array{summary: list<array{key: string, value: int}>, primary: ?array{key: string, value: int}}>
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public array $overview = [];

  /**
   * @var array{metrics: list<array{key: string, value: float, unit: string, max: ?float}>}
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
   * @var array{mode: string, from: ?string, to: ?string, metrics: list<array{key: string, metric: string, label: string, value: ?string, current: ?int, previous: ?int, delta: ?float, direction: ?string}>, health: array{metrics: list<array{key: string, unit: string, max: ?float, current: ?float, previous: ?float, delta: ?float, direction: ?string}>}}
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public array $comparison = ['mode' => 'none', 'from' => null, 'to' => null, 'metrics' => [], 'health' => ['metrics' => []]];
}
