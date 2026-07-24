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

  /**
   * Per-KPI running-total sparkline series, one point per day bucket
   * across the dashboard period (`period.from`..`period.to`). Exact for
   * the default "ends near now" window; an approximation anchored on
   * the current KPI total for explicitly historical windows.
   *
   * @var array{
   *   facilities: list<array{bucket: string, value: int}>,
   *   members: list<array{bucket: string, value: int}>,
   *   equipment: list<array{bucket: string, value: int}>,
   *   inspections: list<array{bucket: string, value: int}>,
   * }
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public array $trends = ['facilities' => [], 'members' => [], 'equipment' => [], 'inspections' => []];

  /**
   * The 5 most recently updated organization interventions across every
   * status, ordered by `updatedAt` descending. Empty when the caller
   * lacks `organization.interventions.read`.
   *
   * @var list<array{
   *   id: string,
   *   number: int,
   *   name: string,
   *   status: string,
   *   priority: string,
   *   siteId: ?string,
   *   siteName: ?string,
   *   responsibleId: ?string,
   *   responsibleName: ?string,
   *   responsibleAvatarUrl: ?string,
   *   dueAt: ?string,
   *   updatedAt: string,
   * }>
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public array $recentInterventions = [];
}
