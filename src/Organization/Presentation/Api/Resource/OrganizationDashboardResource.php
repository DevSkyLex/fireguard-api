<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get};
use ApiPlatform\OpenApi\Model\{Operation, Parameter};
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationDashboardOutput;
use Organization\Presentation\Api\OpenApi\OrganizationDashboardOpenApiValues;
use Organization\Presentation\Api\Operation\OrganizationOperations;
use Organization\Presentation\Api\Provider\Organization\GetOrganizationDashboardProvider;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;

/**
 * Resource OrganizationDashboardResource.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'OrganizationDashboard',
  routePrefix: '/organizations',
  description: 'Organization dashboard analytics and KPIs. Supersedes the legacy /statistics widget endpoints.',
  operations: [
    new Get(
      name: OrganizationOperations::GET_ORGANIZATION_DASHBOARD,
      uriTemplate: '/{organizationId}/dashboard',
      input: false,
      output: OrganizationDashboardOutput::class,
      provider: GetOrganizationDashboardProvider::class,
      normalizationContext: ['groups' => [OrganizationSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      parameters: [
        'from' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string', 'format' => 'date-time', 'example' => '2026-03-01T00:00:00Z'],
          description: 'Inclusive ISO 8601 datetime lower bound for the period-scoped parts of the dashboard, with an explicit timezone offset. Optional microseconds are preserved. If omitted, defaults to 30 days before "to" or now. Maximum supported period: 366 days.',
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(
            name: 'from',
            in: 'query',
            required: false,
            description: 'Inclusive ISO 8601 datetime lower bound for the period-scoped parts of the dashboard, with an explicit timezone offset. Optional microseconds are preserved. If omitted, defaults to 30 days before "to" or now. Maximum supported period: 366 days.',
            schema: ['type' => 'string', 'format' => 'date-time', 'example' => '2026-03-01T00:00:00Z'],
          ),
        ),
        'to' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string', 'format' => 'date-time', 'example' => '2026-03-29T23:59:59Z'],
          description: 'Inclusive ISO 8601 datetime upper bound for the period-scoped parts of the dashboard, with an explicit timezone offset. Optional microseconds are preserved. If omitted, defaults to the current datetime. Maximum supported period: 366 days.',
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(
            name: 'to',
            in: 'query',
            required: false,
            description: 'Inclusive ISO 8601 datetime upper bound for the period-scoped parts of the dashboard, with an explicit timezone offset. Optional microseconds are preserved. If omitted, defaults to the current datetime. Maximum supported period: 366 days.',
            schema: ['type' => 'string', 'format' => 'date-time', 'example' => '2026-03-29T23:59:59Z'],
          ),
        ),
        'compare' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'boolean', 'example' => true],
          description: 'Whether to include previous-period scalar comparison metrics for KPI cards. Defaults to true.',
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(
            name: 'compare',
            in: 'query',
            required: false,
            description: 'Whether to include previous-period scalar comparison metrics for KPI cards. Defaults to true.',
            schema: ['type' => 'boolean', 'default' => true, 'example' => true],
          ),
        ),
        'timezone' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string', 'example' => 'Europe/Paris'],
          description: 'IANA timezone used for rendered period values. Defaults to UTC when omitted and not implied by the request bounds. Required when the requested period spans DST, mixes offsets, or uses non-UTC numeric offsets.',
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(
            name: 'timezone',
            in: 'query',
            required: false,
            description: 'IANA timezone used for rendered period values. Defaults to UTC when omitted and not implied by the request bounds. Required when the requested period spans DST, mixes offsets, or uses non-UTC numeric offsets.',
            schema: ['type' => 'string', 'example' => 'Europe/Paris'],
          ),
        ),
        'facilityType' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string', 'enum' => OrganizationDashboardOpenApiValues::FACILITY_TYPES, 'example' => 'site'],
          description: 'Optional facility type filter applied to the facilities section only.',
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(
            name: 'facilityType',
            in: 'query',
            required: false,
            description: 'Optional facility type filter applied to the facilities section only.',
            schema: ['type' => 'string', 'enum' => OrganizationDashboardOpenApiValues::FACILITY_TYPES, 'example' => 'site'],
          ),
        ),
        'equipmentType' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string', 'enum' => OrganizationDashboardOpenApiValues::EQUIPMENT_TYPES, 'example' => 'fire_extinguisher'],
          description: 'Optional equipment type filter applied to the equipment section only.',
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(
            name: 'equipmentType',
            in: 'query',
            required: false,
            description: 'Optional equipment type filter applied to the equipment section only.',
            schema: ['type' => 'string', 'enum' => OrganizationDashboardOpenApiValues::EQUIPMENT_TYPES, 'example' => 'fire_extinguisher'],
          ),
        ),
        'equipmentStatus' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string', 'enum' => OrganizationDashboardOpenApiValues::EQUIPMENT_STATUSES, 'example' => 'operational'],
          description: 'Optional equipment status filter applied to the equipment section only.',
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(
            name: 'equipmentStatus',
            in: 'query',
            required: false,
            description: 'Optional equipment status filter applied to the equipment section only.',
            schema: ['type' => 'string', 'enum' => OrganizationDashboardOpenApiValues::EQUIPMENT_STATUSES, 'example' => 'operational'],
          ),
        ),
        'inspectionStatus' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string', 'enum' => OrganizationDashboardOpenApiValues::INSPECTION_STATUSES, 'example' => 'closed'],
          description: 'Optional inspection status filter applied to inspection KPIs, period health, and inspection comparisons.',
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(
            name: 'inspectionStatus',
            in: 'query',
            required: false,
            description: 'Optional inspection status filter applied to inspection KPIs, period health, and inspection comparisons.',
            schema: ['type' => 'string', 'enum' => OrganizationDashboardOpenApiValues::INSPECTION_STATUSES, 'example' => 'closed'],
          ),
        ),
        'inspectionResult' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string', 'enum' => OrganizationDashboardOpenApiValues::INSPECTION_RESULTS, 'example' => 'pass'],
          description: 'Optional inspection result filter applied to inspection KPIs, period health, and inspection comparisons.',
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(
            name: 'inspectionResult',
            in: 'query',
            required: false,
            description: 'Optional inspection result filter applied to inspection KPIs, period health, and inspection comparisons.',
            schema: ['type' => 'string', 'enum' => OrganizationDashboardOpenApiValues::INSPECTION_RESULTS, 'example' => 'pass'],
          ),
        ),
        'inspectorType' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string', 'enum' => OrganizationDashboardOpenApiValues::INSPECTOR_TYPES, 'example' => 'user'],
          description: 'Optional inspector type filter applied to inspection KPIs, period health, and inspection comparisons.',
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(
            name: 'inspectorType',
            in: 'query',
            required: false,
            description: 'Optional inspector type filter applied to inspection KPIs, period health, and inspection comparisons.',
            schema: ['type' => 'string', 'enum' => OrganizationDashboardOpenApiValues::INSPECTOR_TYPES, 'example' => 'user'],
          ),
        ),
        'nonConformityStatus' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string', 'enum' => OrganizationDashboardOpenApiValues::NON_CONFORMITY_STATUSES, 'example' => 'open'],
          description: 'Optional non-conformity status filter applied to non-conformity KPIs, alerts, period health, and non-conformity comparisons.',
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(
            name: 'nonConformityStatus',
            in: 'query',
            required: false,
            description: 'Optional non-conformity status filter applied to non-conformity KPIs, alerts, period health, and non-conformity comparisons.',
            schema: ['type' => 'string', 'enum' => OrganizationDashboardOpenApiValues::NON_CONFORMITY_STATUSES, 'example' => 'open'],
          ),
        ),
        'nonConformitySeverity' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string', 'enum' => OrganizationDashboardOpenApiValues::NON_CONFORMITY_SEVERITIES, 'example' => 'critical'],
          description: 'Optional non-conformity severity filter applied to non-conformity KPIs, alerts, period health, and non-conformity comparisons.',
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(
            name: 'nonConformitySeverity',
            in: 'query',
            required: false,
            description: 'Optional non-conformity severity filter applied to non-conformity KPIs, alerts, period health, and non-conformity comparisons.',
            schema: ['type' => 'string', 'enum' => OrganizationDashboardOpenApiValues::NON_CONFORMITY_SEVERITIES, 'example' => 'critical'],
          ),
        ),
      ],
      openapi: new Operation(
        tags: ['Organization'],
        summary: 'Get organization dashboard',
        description: 'Returns lightweight organization-overview KPI data. `overview`, `alerts`, and non-`period*` health KPIs are current snapshots evaluated at `generatedAt`; `comparison` and `period*` health KPIs follow the requested period. `overview` is normalized for frontend consumption and exposes only stable keys and `summary` metric lists. `alerts` expose machine-readable `code`/`severity`/`count`, `health` exposes an iterable `metrics` list, and `comparison.metrics` exposes stable KPI cards for inspections, facilities, members, and equipment with `key`/`label`/`value` plus raw `current`/`previous`/`delta` values. Those cards represent period-over-period changes for inspections performed, facilities created, members joined, and equipment created. Legacy non-conformity opened and resolved comparison entries remain present for backward compatibility. `comparison.health.metrics` exposes percentage-based health deltas. Analytics filters are section-scoped: `facilityType` affects facilities overview and facility comparison cards, `equipmentType` and `equipmentStatus` affect equipment overview and equipment comparison cards, `inspection*` filters affect inspection KPIs and period comparisons, and `nonConformity*` filters affect non-conformity KPIs, alerts, and period comparisons. `overview.nonConformities.severityLow`/`severityMedium`/`severityHigh`/`severityCritical` are the sole exception: an org-wide, unfiltered current-snapshot breakdown by severity across every status, unaffected by `nonConformityStatus`/`nonConformitySeverity`. By default, the endpoint returns the latest 30-day window in UTC and compares it with the previous period. The maximum supported period is 366 days. Datetime bounds must be ISO 8601 strings with an explicit timezone offset, including optional microseconds. Access requires `organization.dashboard.read` plus the underlying read permissions for members, roles, facilities, equipment, and inspections. For chart or sparkline data, use the dedicated `/dashboard/trends/*` endpoints, which require `organization.inspection.read`.',
        parameters: [],
      ),
    ),
  ],
)]
final class OrganizationDashboardResource
{
}
