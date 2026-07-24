<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get};
use ApiPlatform\OpenApi\Model\{Operation, Parameter};
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationDashboardTrendOutput;
use Organization\Presentation\Api\OpenApi\OrganizationDashboardOpenApiValues;
use Organization\Presentation\Api\Operation\OrganizationOperations;
use Organization\Presentation\Api\Provider\Organization\GetOrganizationDashboardTrendProvider;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;

/**
 * Resource OrganizationDashboardTrendResource.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'OrganizationDashboardTrend',
  routePrefix: '/organizations',
  description: 'Organization dashboard trend endpoints for chart-level requests with independent periods.',
  operations: [
    new Get(
      name: OrganizationOperations::GET_ORGANIZATION_DASHBOARD_INSPECTIONS_TREND,
      uriTemplate: '/{organizationId}/dashboard/trends/inspections',
      input: false,
      output: OrganizationDashboardTrendOutput::class,
      provider: GetOrganizationDashboardTrendProvider::class,
      normalizationContext: ['groups' => [OrganizationSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Organization'],
        summary: 'Get inspections trend',
        description: 'Returns the inspections-performed trend as a single chart-ready series. Use this endpoint when a chart needs its own independent period or granularity, separate from the aggregate `/dashboard` payload. Access requires `organization.inspection.read`.',
        parameters: [
          new Parameter(
            name: 'from',
            in: 'query',
            required: false,
            description: 'Inclusive ISO 8601 datetime lower bound for the trend period, with an explicit timezone offset. Optional microseconds are preserved.',
            schema: ['type' => 'string', 'format' => 'date-time', 'example' => '2026-03-01T00:00:00Z'],
          ),
          new Parameter(
            name: 'to',
            in: 'query',
            required: false,
            description: 'Inclusive ISO 8601 datetime upper bound for the trend period, with an explicit timezone offset. Optional microseconds are preserved.',
            schema: ['type' => 'string', 'format' => 'date-time', 'example' => '2026-03-29T23:59:59Z'],
          ),
          new Parameter(
            name: 'compare',
            in: 'query',
            required: false,
            description: 'Whether to include previous-period comparison series. Defaults to true.',
            schema: ['type' => 'boolean', 'default' => true, 'example' => true],
          ),
          new Parameter(
            name: 'granularity',
            in: 'query',
            required: false,
            description: 'Trend aggregation granularity. Allowed values: day, week, month, auto. Defaults to day.',
            schema: ['type' => 'string', 'enum' => OrganizationDashboardOpenApiValues::GRANULARITIES, 'default' => 'day', 'example' => 'week'],
          ),
          new Parameter(
            name: 'timezone',
            in: 'query',
            required: false,
            description: 'IANA timezone used for bucket boundaries and rendered period values. Required when the requested period spans DST, mixes offsets, or uses non-UTC numeric offsets.',
            schema: ['type' => 'string', 'example' => 'Europe/Paris'],
          ),
          new Parameter(
            name: 'inspectionStatus',
            in: 'query',
            required: false,
            description: 'Optional inspection status filter applied to the inspection trend only.',
            schema: ['type' => 'string', 'enum' => OrganizationDashboardOpenApiValues::INSPECTION_STATUSES, 'example' => 'closed'],
          ),
          new Parameter(
            name: 'inspectionResult',
            in: 'query',
            required: false,
            description: 'Optional inspection result filter applied to the inspection trend only.',
            schema: ['type' => 'string', 'enum' => OrganizationDashboardOpenApiValues::INSPECTION_RESULTS, 'example' => 'pass'],
          ),
          new Parameter(
            name: 'inspectorType',
            in: 'query',
            required: false,
            description: 'Optional inspector type filter applied to the inspection trend only.',
            schema: ['type' => 'string', 'enum' => OrganizationDashboardOpenApiValues::INSPECTOR_TYPES, 'example' => 'user'],
          ),
        ],
      ),
    ),
    new Get(
      name: OrganizationOperations::GET_ORGANIZATION_DASHBOARD_EQUIPMENT_CREATED_TREND,
      uriTemplate: '/{organizationId}/dashboard/trends/equipment-created',
      input: false,
      output: OrganizationDashboardTrendOutput::class,
      provider: GetOrganizationDashboardTrendProvider::class,
      normalizationContext: ['groups' => [OrganizationSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Organization'],
        summary: 'Get equipment created trend',
        description: 'Returns the equipment-created trend as a single chart-ready series. Use this endpoint when a chart needs its own independent period or granularity, separate from the aggregate `/dashboard` payload. Access requires `organization.equipment.read`.',
        parameters: [
          new Parameter(
            name: 'from',
            in: 'query',
            required: false,
            description: 'Inclusive ISO 8601 datetime lower bound for the trend period, with an explicit timezone offset. Optional microseconds are preserved.',
            schema: ['type' => 'string', 'format' => 'date-time', 'example' => '2026-03-01T00:00:00Z'],
          ),
          new Parameter(
            name: 'to',
            in: 'query',
            required: false,
            description: 'Inclusive ISO 8601 datetime upper bound for the trend period, with an explicit timezone offset. Optional microseconds are preserved.',
            schema: ['type' => 'string', 'format' => 'date-time', 'example' => '2026-03-29T23:59:59Z'],
          ),
          new Parameter(
            name: 'compare',
            in: 'query',
            required: false,
            description: 'Whether to include previous-period comparison series. Defaults to true.',
            schema: ['type' => 'boolean', 'default' => true, 'example' => true],
          ),
          new Parameter(
            name: 'granularity',
            in: 'query',
            required: false,
            description: 'Trend aggregation granularity. Allowed values: day, week, month, auto. Defaults to day.',
            schema: ['type' => 'string', 'enum' => OrganizationDashboardOpenApiValues::GRANULARITIES, 'default' => 'day', 'example' => 'week'],
          ),
          new Parameter(
            name: 'timezone',
            in: 'query',
            required: false,
            description: 'IANA timezone used for bucket boundaries and rendered period values. Required when the requested period spans DST, mixes offsets, or uses non-UTC numeric offsets.',
            schema: ['type' => 'string', 'example' => 'Europe/Paris'],
          ),
          new Parameter(
            name: 'equipmentType',
            in: 'query',
            required: false,
            description: 'Optional equipment type filter applied to the equipment-created trend only.',
            schema: ['type' => 'string', 'enum' => OrganizationDashboardOpenApiValues::EQUIPMENT_TYPES, 'example' => 'fire_extinguisher'],
          ),
          new Parameter(
            name: 'equipmentStatus',
            in: 'query',
            required: false,
            description: 'Optional equipment status filter applied to the equipment-created trend only.',
            schema: ['type' => 'string', 'enum' => OrganizationDashboardOpenApiValues::EQUIPMENT_STATUSES, 'example' => 'operational'],
          ),
        ],
      ),
    ),
    new Get(
      name: OrganizationOperations::GET_ORGANIZATION_DASHBOARD_FACILITIES_CREATED_TREND,
      uriTemplate: '/{organizationId}/dashboard/trends/facilities-created',
      input: false,
      output: OrganizationDashboardTrendOutput::class,
      provider: GetOrganizationDashboardTrendProvider::class,
      normalizationContext: ['groups' => [OrganizationSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Organization'],
        summary: 'Get facilities created trend',
        description: 'Returns the facilities-created trend as a single chart-ready series. Use this endpoint when a chart needs its own independent period or granularity, separate from the aggregate `/dashboard` payload. Access requires `organization.facilities.read`.',
        parameters: [
          new Parameter(
            name: 'from',
            in: 'query',
            required: false,
            description: 'Inclusive ISO 8601 datetime lower bound for the trend period, with an explicit timezone offset. Optional microseconds are preserved.',
            schema: ['type' => 'string', 'format' => 'date-time', 'example' => '2026-03-01T00:00:00Z'],
          ),
          new Parameter(
            name: 'to',
            in: 'query',
            required: false,
            description: 'Inclusive ISO 8601 datetime upper bound for the trend period, with an explicit timezone offset. Optional microseconds are preserved.',
            schema: ['type' => 'string', 'format' => 'date-time', 'example' => '2026-03-29T23:59:59Z'],
          ),
          new Parameter(
            name: 'compare',
            in: 'query',
            required: false,
            description: 'Whether to include previous-period comparison series. Defaults to true.',
            schema: ['type' => 'boolean', 'default' => true, 'example' => true],
          ),
          new Parameter(
            name: 'granularity',
            in: 'query',
            required: false,
            description: 'Trend aggregation granularity. Allowed values: day, week, month, auto. Defaults to day.',
            schema: ['type' => 'string', 'enum' => OrganizationDashboardOpenApiValues::GRANULARITIES, 'default' => 'day', 'example' => 'week'],
          ),
          new Parameter(
            name: 'timezone',
            in: 'query',
            required: false,
            description: 'IANA timezone used for bucket boundaries and rendered period values. Required when the requested period spans DST, mixes offsets, or uses non-UTC numeric offsets.',
            schema: ['type' => 'string', 'example' => 'Europe/Paris'],
          ),
          new Parameter(
            name: 'facilityType',
            in: 'query',
            required: false,
            description: 'Optional facility type filter applied to the facilities-created trend only.',
            schema: ['type' => 'string', 'enum' => OrganizationDashboardOpenApiValues::FACILITY_TYPES, 'example' => 'site'],
          ),
        ],
      ),
    ),
    new Get(
      name: OrganizationOperations::GET_ORGANIZATION_DASHBOARD_NON_CONFORMITIES_OPENED_TREND,
      uriTemplate: '/{organizationId}/dashboard/trends/non-conformities-opened',
      input: false,
      output: OrganizationDashboardTrendOutput::class,
      provider: GetOrganizationDashboardTrendProvider::class,
      normalizationContext: ['groups' => [OrganizationSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Organization'],
        summary: 'Get non-conformities opened trend',
        description: 'Returns the non-conformities-opened trend as a single chart-ready series. Use this endpoint when a chart needs its own independent period or granularity, separate from the aggregate `/dashboard` payload. Access requires `organization.inspection.read`.',
        parameters: [
          new Parameter(
            name: 'from',
            in: 'query',
            required: false,
            description: 'Inclusive ISO 8601 datetime lower bound for the trend period, with an explicit timezone offset. Optional microseconds are preserved.',
            schema: ['type' => 'string', 'format' => 'date-time', 'example' => '2026-03-01T00:00:00Z'],
          ),
          new Parameter(
            name: 'to',
            in: 'query',
            required: false,
            description: 'Inclusive ISO 8601 datetime upper bound for the trend period, with an explicit timezone offset. Optional microseconds are preserved.',
            schema: ['type' => 'string', 'format' => 'date-time', 'example' => '2026-03-29T23:59:59Z'],
          ),
          new Parameter(
            name: 'compare',
            in: 'query',
            required: false,
            description: 'Whether to include previous-period comparison series. Defaults to true.',
            schema: ['type' => 'boolean', 'default' => true, 'example' => true],
          ),
          new Parameter(
            name: 'granularity',
            in: 'query',
            required: false,
            description: 'Trend aggregation granularity. Allowed values: day, week, month, auto. Defaults to day.',
            schema: ['type' => 'string', 'enum' => OrganizationDashboardOpenApiValues::GRANULARITIES, 'default' => 'day', 'example' => 'week'],
          ),
          new Parameter(
            name: 'timezone',
            in: 'query',
            required: false,
            description: 'IANA timezone used for bucket boundaries and rendered period values. Required when the requested period spans DST, mixes offsets, or uses non-UTC numeric offsets.',
            schema: ['type' => 'string', 'example' => 'Europe/Paris'],
          ),
          new Parameter(
            name: 'nonConformityStatus',
            in: 'query',
            required: false,
            description: 'Optional non-conformity status filter applied to the non-conformity trend only.',
            schema: ['type' => 'string', 'enum' => OrganizationDashboardOpenApiValues::NON_CONFORMITY_STATUSES, 'example' => 'open'],
          ),
          new Parameter(
            name: 'nonConformitySeverity',
            in: 'query',
            required: false,
            description: 'Optional non-conformity severity filter applied to the non-conformity trend only.',
            schema: ['type' => 'string', 'enum' => OrganizationDashboardOpenApiValues::NON_CONFORMITY_SEVERITIES, 'example' => 'critical'],
          ),
          new Parameter(
            name: 'metrics',
            in: 'query',
            required: false,
            description: 'Optional comma-separated list of additional non-conformity metric identifiers to combine into `seriesByMetric`, sharing this call\'s resolved period, timezone and granularity so a two-series (opened vs resolved) chart can render from one request instead of two independently-bucketed calls. Allowed values: non_conformities_opened, non_conformities_resolved.',
            schema: ['type' => 'string', 'example' => 'non_conformities_opened,non_conformities_resolved'],
          ),
        ],
      ),
    ),
    new Get(
      name: OrganizationOperations::GET_ORGANIZATION_DASHBOARD_NON_CONFORMITIES_RESOLVED_TREND,
      uriTemplate: '/{organizationId}/dashboard/trends/non-conformities-resolved',
      input: false,
      output: OrganizationDashboardTrendOutput::class,
      provider: GetOrganizationDashboardTrendProvider::class,
      normalizationContext: ['groups' => [OrganizationSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Organization'],
        summary: 'Get non-conformities resolved trend',
        description: 'Returns the non-conformities-resolved trend as a single chart-ready series. Use this endpoint when a chart needs its own independent period or granularity, separate from the aggregate `/dashboard` payload. Access requires `organization.inspection.read`.',
        parameters: [
          new Parameter(
            name: 'from',
            in: 'query',
            required: false,
            description: 'Inclusive ISO 8601 datetime lower bound for the trend period, with an explicit timezone offset. Optional microseconds are preserved.',
            schema: ['type' => 'string', 'format' => 'date-time', 'example' => '2026-03-01T00:00:00Z'],
          ),
          new Parameter(
            name: 'to',
            in: 'query',
            required: false,
            description: 'Inclusive ISO 8601 datetime upper bound for the trend period, with an explicit timezone offset. Optional microseconds are preserved.',
            schema: ['type' => 'string', 'format' => 'date-time', 'example' => '2026-03-29T23:59:59Z'],
          ),
          new Parameter(
            name: 'compare',
            in: 'query',
            required: false,
            description: 'Whether to include previous-period comparison series. Defaults to true.',
            schema: ['type' => 'boolean', 'default' => true, 'example' => true],
          ),
          new Parameter(
            name: 'granularity',
            in: 'query',
            required: false,
            description: 'Trend aggregation granularity. Allowed values: day, week, month, auto. Defaults to day.',
            schema: ['type' => 'string', 'enum' => OrganizationDashboardOpenApiValues::GRANULARITIES, 'default' => 'day', 'example' => 'week'],
          ),
          new Parameter(
            name: 'timezone',
            in: 'query',
            required: false,
            description: 'IANA timezone used for bucket boundaries and rendered period values. Required when the requested period spans DST, mixes offsets, or uses non-UTC numeric offsets.',
            schema: ['type' => 'string', 'example' => 'Europe/Paris'],
          ),
          new Parameter(
            name: 'nonConformityStatus',
            in: 'query',
            required: false,
            description: 'Optional non-conformity status filter applied to the non-conformity trend only.',
            schema: ['type' => 'string', 'enum' => OrganizationDashboardOpenApiValues::NON_CONFORMITY_STATUSES, 'example' => 'open'],
          ),
          new Parameter(
            name: 'nonConformitySeverity',
            in: 'query',
            required: false,
            description: 'Optional non-conformity severity filter applied to the non-conformity trend only.',
            schema: ['type' => 'string', 'enum' => OrganizationDashboardOpenApiValues::NON_CONFORMITY_SEVERITIES, 'example' => 'critical'],
          ),
          new Parameter(
            name: 'metrics',
            in: 'query',
            required: false,
            description: 'Optional comma-separated list of additional non-conformity metric identifiers to combine into `seriesByMetric`, sharing this call\'s resolved period, timezone and granularity so a two-series (opened vs resolved) chart can render from one request instead of two independently-bucketed calls. Allowed values: non_conformities_opened, non_conformities_resolved.',
            schema: ['type' => 'string', 'example' => 'non_conformities_opened,non_conformities_resolved'],
          ),
        ],
      ),
    ),
  ],
)]
final class OrganizationDashboardTrendResource
{
}
