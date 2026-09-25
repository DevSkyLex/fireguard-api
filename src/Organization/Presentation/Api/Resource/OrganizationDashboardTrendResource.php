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
      security: self::SECURITY_ROLE_USER,
      parameters: [
        'from' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string', 'format' => 'date-time', 'example' => self::EXAMPLE_FROM_DATE],
          description: self::FROM_FILTER_DESCRIPTION,
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(
            name: 'from',
            in: 'query',
            required: false,
            description: self::FROM_FILTER_DESCRIPTION,
            schema: ['type' => 'string', 'format' => 'date-time', 'example' => self::EXAMPLE_FROM_DATE],
          ),
        ),
        'to' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string', 'format' => 'date-time', 'example' => self::EXAMPLE_TO_DATE],
          description: self::TO_FILTER_DESCRIPTION,
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(
            name: 'to',
            in: 'query',
            required: false,
            description: self::TO_FILTER_DESCRIPTION,
            schema: ['type' => 'string', 'format' => 'date-time', 'example' => self::EXAMPLE_TO_DATE],
          ),
        ),
        'compare' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'boolean', 'example' => true],
          description: self::COMPARE_FILTER_DESCRIPTION,
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(
            name: 'compare',
            in: 'query',
            required: false,
            description: self::COMPARE_FILTER_DESCRIPTION,
            schema: ['type' => 'boolean', 'default' => true, 'example' => true],
          ),
        ),
        'granularity' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string', 'enum' => OrganizationDashboardOpenApiValues::GRANULARITIES, 'example' => 'week'],
          description: self::GRANULARITY_FILTER_DESCRIPTION,
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(
            name: 'granularity',
            in: 'query',
            required: false,
            description: self::GRANULARITY_FILTER_DESCRIPTION,
            schema: ['type' => 'string', 'enum' => OrganizationDashboardOpenApiValues::GRANULARITIES, 'default' => 'day', 'example' => 'week'],
          ),
        ),
        'timezone' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string', 'example' => self::EXAMPLE_TIMEZONE],
          description: self::TIMEZONE_FILTER_DESCRIPTION,
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(
            name: 'timezone',
            in: 'query',
            required: false,
            description: self::TIMEZONE_FILTER_DESCRIPTION,
            schema: ['type' => 'string', 'example' => self::EXAMPLE_TIMEZONE],
          ),
        ),
        'inspectionStatus' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string', 'enum' => OrganizationDashboardOpenApiValues::INSPECTION_STATUSES, 'example' => 'closed'],
          description: 'Optional inspection status filter applied to the inspection trend only.',
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(
            name: 'inspectionStatus',
            in: 'query',
            required: false,
            description: 'Optional inspection status filter applied to the inspection trend only.',
            schema: ['type' => 'string', 'enum' => OrganizationDashboardOpenApiValues::INSPECTION_STATUSES, 'example' => 'closed'],
          ),
        ),
        'inspectionResult' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string', 'enum' => OrganizationDashboardOpenApiValues::INSPECTION_RESULTS, 'example' => 'pass'],
          description: 'Optional inspection result filter applied to the inspection trend only.',
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(
            name: 'inspectionResult',
            in: 'query',
            required: false,
            description: 'Optional inspection result filter applied to the inspection trend only.',
            schema: ['type' => 'string', 'enum' => OrganizationDashboardOpenApiValues::INSPECTION_RESULTS, 'example' => 'pass'],
          ),
        ),
        'inspectorType' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string', 'enum' => OrganizationDashboardOpenApiValues::INSPECTOR_TYPES, 'example' => 'user'],
          description: 'Optional inspector type filter applied to the inspection trend only.',
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(
            name: 'inspectorType',
            in: 'query',
            required: false,
            description: 'Optional inspector type filter applied to the inspection trend only.',
            schema: ['type' => 'string', 'enum' => OrganizationDashboardOpenApiValues::INSPECTOR_TYPES, 'example' => 'user'],
          ),
        ),
      ],
      openapi: new Operation(
        tags: ['Organization'],
        summary: 'Get inspections trend',
        description: 'Returns the inspections-performed trend as a single chart-ready series. Use this endpoint when a chart needs its own independent period or granularity, separate from the aggregate `/dashboard` payload. Access requires `organization.inspection.read`.',
        parameters: [],
      ),
    ),
    new Get(
      name: OrganizationOperations::GET_ORGANIZATION_DASHBOARD_EQUIPMENT_CREATED_TREND,
      uriTemplate: '/{organizationId}/dashboard/trends/equipment-created',
      input: false,
      output: OrganizationDashboardTrendOutput::class,
      provider: GetOrganizationDashboardTrendProvider::class,
      normalizationContext: ['groups' => [OrganizationSerializationGroup::READ]],
      security: self::SECURITY_ROLE_USER,
      parameters: [
        'from' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string', 'format' => 'date-time', 'example' => self::EXAMPLE_FROM_DATE],
          description: self::FROM_FILTER_DESCRIPTION,
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(
            name: 'from',
            in: 'query',
            required: false,
            description: self::FROM_FILTER_DESCRIPTION,
            schema: ['type' => 'string', 'format' => 'date-time', 'example' => self::EXAMPLE_FROM_DATE],
          ),
        ),
        'to' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string', 'format' => 'date-time', 'example' => self::EXAMPLE_TO_DATE],
          description: self::TO_FILTER_DESCRIPTION,
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(
            name: 'to',
            in: 'query',
            required: false,
            description: self::TO_FILTER_DESCRIPTION,
            schema: ['type' => 'string', 'format' => 'date-time', 'example' => self::EXAMPLE_TO_DATE],
          ),
        ),
        'compare' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'boolean', 'example' => true],
          description: self::COMPARE_FILTER_DESCRIPTION,
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(
            name: 'compare',
            in: 'query',
            required: false,
            description: self::COMPARE_FILTER_DESCRIPTION,
            schema: ['type' => 'boolean', 'default' => true, 'example' => true],
          ),
        ),
        'granularity' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string', 'enum' => OrganizationDashboardOpenApiValues::GRANULARITIES, 'example' => 'week'],
          description: self::GRANULARITY_FILTER_DESCRIPTION,
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(
            name: 'granularity',
            in: 'query',
            required: false,
            description: self::GRANULARITY_FILTER_DESCRIPTION,
            schema: ['type' => 'string', 'enum' => OrganizationDashboardOpenApiValues::GRANULARITIES, 'default' => 'day', 'example' => 'week'],
          ),
        ),
        'timezone' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string', 'example' => self::EXAMPLE_TIMEZONE],
          description: self::TIMEZONE_FILTER_DESCRIPTION,
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(
            name: 'timezone',
            in: 'query',
            required: false,
            description: self::TIMEZONE_FILTER_DESCRIPTION,
            schema: ['type' => 'string', 'example' => self::EXAMPLE_TIMEZONE],
          ),
        ),
        'equipmentType' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string', 'enum' => OrganizationDashboardOpenApiValues::EQUIPMENT_TYPES, 'example' => 'fire_extinguisher'],
          description: 'Optional equipment type filter applied to the equipment-created trend only.',
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(
            name: 'equipmentType',
            in: 'query',
            required: false,
            description: 'Optional equipment type filter applied to the equipment-created trend only.',
            schema: ['type' => 'string', 'enum' => OrganizationDashboardOpenApiValues::EQUIPMENT_TYPES, 'example' => 'fire_extinguisher'],
          ),
        ),
        'equipmentStatus' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string', 'enum' => OrganizationDashboardOpenApiValues::EQUIPMENT_STATUSES, 'example' => 'operational'],
          description: 'Optional equipment status filter applied to the equipment-created trend only.',
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(
            name: 'equipmentStatus',
            in: 'query',
            required: false,
            description: 'Optional equipment status filter applied to the equipment-created trend only.',
            schema: ['type' => 'string', 'enum' => OrganizationDashboardOpenApiValues::EQUIPMENT_STATUSES, 'example' => 'operational'],
          ),
        ),
      ],
      openapi: new Operation(
        tags: ['Organization'],
        summary: 'Get equipment created trend',
        description: 'Returns the equipment-created trend as a single chart-ready series. Use this endpoint when a chart needs its own independent period or granularity, separate from the aggregate `/dashboard` payload. Access requires `organization.equipment.read`.',
        parameters: [],
      ),
    ),
    new Get(
      name: OrganizationOperations::GET_ORGANIZATION_DASHBOARD_FACILITIES_CREATED_TREND,
      uriTemplate: '/{organizationId}/dashboard/trends/facilities-created',
      input: false,
      output: OrganizationDashboardTrendOutput::class,
      provider: GetOrganizationDashboardTrendProvider::class,
      normalizationContext: ['groups' => [OrganizationSerializationGroup::READ]],
      security: self::SECURITY_ROLE_USER,
      parameters: [
        'from' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string', 'format' => 'date-time', 'example' => self::EXAMPLE_FROM_DATE],
          description: self::FROM_FILTER_DESCRIPTION,
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(
            name: 'from',
            in: 'query',
            required: false,
            description: self::FROM_FILTER_DESCRIPTION,
            schema: ['type' => 'string', 'format' => 'date-time', 'example' => self::EXAMPLE_FROM_DATE],
          ),
        ),
        'to' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string', 'format' => 'date-time', 'example' => self::EXAMPLE_TO_DATE],
          description: self::TO_FILTER_DESCRIPTION,
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(
            name: 'to',
            in: 'query',
            required: false,
            description: self::TO_FILTER_DESCRIPTION,
            schema: ['type' => 'string', 'format' => 'date-time', 'example' => self::EXAMPLE_TO_DATE],
          ),
        ),
        'compare' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'boolean', 'example' => true],
          description: self::COMPARE_FILTER_DESCRIPTION,
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(
            name: 'compare',
            in: 'query',
            required: false,
            description: self::COMPARE_FILTER_DESCRIPTION,
            schema: ['type' => 'boolean', 'default' => true, 'example' => true],
          ),
        ),
        'granularity' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string', 'enum' => OrganizationDashboardOpenApiValues::GRANULARITIES, 'example' => 'week'],
          description: self::GRANULARITY_FILTER_DESCRIPTION,
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(
            name: 'granularity',
            in: 'query',
            required: false,
            description: self::GRANULARITY_FILTER_DESCRIPTION,
            schema: ['type' => 'string', 'enum' => OrganizationDashboardOpenApiValues::GRANULARITIES, 'default' => 'day', 'example' => 'week'],
          ),
        ),
        'timezone' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string', 'example' => self::EXAMPLE_TIMEZONE],
          description: self::TIMEZONE_FILTER_DESCRIPTION,
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(
            name: 'timezone',
            in: 'query',
            required: false,
            description: self::TIMEZONE_FILTER_DESCRIPTION,
            schema: ['type' => 'string', 'example' => self::EXAMPLE_TIMEZONE],
          ),
        ),
        'facilityType' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string', 'enum' => OrganizationDashboardOpenApiValues::FACILITY_TYPES, 'example' => 'site'],
          description: 'Optional facility type filter applied to the facilities-created trend only.',
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(
            name: 'facilityType',
            in: 'query',
            required: false,
            description: 'Optional facility type filter applied to the facilities-created trend only.',
            schema: ['type' => 'string', 'enum' => OrganizationDashboardOpenApiValues::FACILITY_TYPES, 'example' => 'site'],
          ),
        ),
      ],
      openapi: new Operation(
        tags: ['Organization'],
        summary: 'Get facilities created trend',
        description: 'Returns the facilities-created trend as a single chart-ready series. Use this endpoint when a chart needs its own independent period or granularity, separate from the aggregate `/dashboard` payload. Access requires `organization.facilities.read`.',
        parameters: [],
      ),
    ),
    new Get(
      name: OrganizationOperations::GET_ORGANIZATION_DASHBOARD_NON_CONFORMITIES_OPENED_TREND,
      uriTemplate: '/{organizationId}/dashboard/trends/non-conformities-opened',
      input: false,
      output: OrganizationDashboardTrendOutput::class,
      provider: GetOrganizationDashboardTrendProvider::class,
      normalizationContext: ['groups' => [OrganizationSerializationGroup::READ]],
      security: self::SECURITY_ROLE_USER,
      parameters: [
        'from' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string', 'format' => 'date-time', 'example' => self::EXAMPLE_FROM_DATE],
          description: self::FROM_FILTER_DESCRIPTION,
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(
            name: 'from',
            in: 'query',
            required: false,
            description: self::FROM_FILTER_DESCRIPTION,
            schema: ['type' => 'string', 'format' => 'date-time', 'example' => self::EXAMPLE_FROM_DATE],
          ),
        ),
        'to' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string', 'format' => 'date-time', 'example' => self::EXAMPLE_TO_DATE],
          description: self::TO_FILTER_DESCRIPTION,
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(
            name: 'to',
            in: 'query',
            required: false,
            description: self::TO_FILTER_DESCRIPTION,
            schema: ['type' => 'string', 'format' => 'date-time', 'example' => self::EXAMPLE_TO_DATE],
          ),
        ),
        'compare' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'boolean', 'example' => true],
          description: self::COMPARE_FILTER_DESCRIPTION,
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(
            name: 'compare',
            in: 'query',
            required: false,
            description: self::COMPARE_FILTER_DESCRIPTION,
            schema: ['type' => 'boolean', 'default' => true, 'example' => true],
          ),
        ),
        'granularity' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string', 'enum' => OrganizationDashboardOpenApiValues::GRANULARITIES, 'example' => 'week'],
          description: self::GRANULARITY_FILTER_DESCRIPTION,
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(
            name: 'granularity',
            in: 'query',
            required: false,
            description: self::GRANULARITY_FILTER_DESCRIPTION,
            schema: ['type' => 'string', 'enum' => OrganizationDashboardOpenApiValues::GRANULARITIES, 'default' => 'day', 'example' => 'week'],
          ),
        ),
        'timezone' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string', 'example' => self::EXAMPLE_TIMEZONE],
          description: self::TIMEZONE_FILTER_DESCRIPTION,
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(
            name: 'timezone',
            in: 'query',
            required: false,
            description: self::TIMEZONE_FILTER_DESCRIPTION,
            schema: ['type' => 'string', 'example' => self::EXAMPLE_TIMEZONE],
          ),
        ),
        'nonConformityStatus' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string', 'enum' => OrganizationDashboardOpenApiValues::NON_CONFORMITY_STATUSES, 'example' => 'open'],
          description: self::NON_CONFORMITY_STATUS_FILTER_DESCRIPTION,
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(
            name: 'nonConformityStatus',
            in: 'query',
            required: false,
            description: self::NON_CONFORMITY_STATUS_FILTER_DESCRIPTION,
            schema: ['type' => 'string', 'enum' => OrganizationDashboardOpenApiValues::NON_CONFORMITY_STATUSES, 'example' => 'open'],
          ),
        ),
        'nonConformitySeverity' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string', 'enum' => OrganizationDashboardOpenApiValues::NON_CONFORMITY_SEVERITIES, 'example' => 'critical'],
          description: self::NON_CONFORMITY_SEVERITY_FILTER_DESCRIPTION,
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(
            name: 'nonConformitySeverity',
            in: 'query',
            required: false,
            description: self::NON_CONFORMITY_SEVERITY_FILTER_DESCRIPTION,
            schema: ['type' => 'string', 'enum' => OrganizationDashboardOpenApiValues::NON_CONFORMITY_SEVERITIES, 'example' => 'critical'],
          ),
        ),
        'metrics' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string', 'example' => 'non_conformities_opened,non_conformities_resolved'],
          description: self::METRICS_FILTER_DESCRIPTION,
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(
            name: 'metrics',
            in: 'query',
            required: false,
            description: self::METRICS_FILTER_DESCRIPTION,
            schema: ['type' => 'string', 'example' => 'non_conformities_opened,non_conformities_resolved'],
          ),
        ),
      ],
      openapi: new Operation(
        tags: ['Organization'],
        summary: 'Get non-conformities opened trend',
        description: 'Returns the non-conformities-opened trend as a single chart-ready series. Use this endpoint when a chart needs its own independent period or granularity, separate from the aggregate `/dashboard` payload. Access requires `organization.inspection.read`.',
        parameters: [],
      ),
    ),
    new Get(
      name: OrganizationOperations::GET_ORGANIZATION_DASHBOARD_NON_CONFORMITIES_RESOLVED_TREND,
      uriTemplate: '/{organizationId}/dashboard/trends/non-conformities-resolved',
      input: false,
      output: OrganizationDashboardTrendOutput::class,
      provider: GetOrganizationDashboardTrendProvider::class,
      normalizationContext: ['groups' => [OrganizationSerializationGroup::READ]],
      security: self::SECURITY_ROLE_USER,
      parameters: [
        'from' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string', 'format' => 'date-time', 'example' => self::EXAMPLE_FROM_DATE],
          description: self::FROM_FILTER_DESCRIPTION,
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(
            name: 'from',
            in: 'query',
            required: false,
            description: self::FROM_FILTER_DESCRIPTION,
            schema: ['type' => 'string', 'format' => 'date-time', 'example' => self::EXAMPLE_FROM_DATE],
          ),
        ),
        'to' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string', 'format' => 'date-time', 'example' => self::EXAMPLE_TO_DATE],
          description: self::TO_FILTER_DESCRIPTION,
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(
            name: 'to',
            in: 'query',
            required: false,
            description: self::TO_FILTER_DESCRIPTION,
            schema: ['type' => 'string', 'format' => 'date-time', 'example' => self::EXAMPLE_TO_DATE],
          ),
        ),
        'compare' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'boolean', 'example' => true],
          description: self::COMPARE_FILTER_DESCRIPTION,
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(
            name: 'compare',
            in: 'query',
            required: false,
            description: self::COMPARE_FILTER_DESCRIPTION,
            schema: ['type' => 'boolean', 'default' => true, 'example' => true],
          ),
        ),
        'granularity' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string', 'enum' => OrganizationDashboardOpenApiValues::GRANULARITIES, 'example' => 'week'],
          description: self::GRANULARITY_FILTER_DESCRIPTION,
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(
            name: 'granularity',
            in: 'query',
            required: false,
            description: self::GRANULARITY_FILTER_DESCRIPTION,
            schema: ['type' => 'string', 'enum' => OrganizationDashboardOpenApiValues::GRANULARITIES, 'default' => 'day', 'example' => 'week'],
          ),
        ),
        'timezone' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string', 'example' => self::EXAMPLE_TIMEZONE],
          description: self::TIMEZONE_FILTER_DESCRIPTION,
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(
            name: 'timezone',
            in: 'query',
            required: false,
            description: self::TIMEZONE_FILTER_DESCRIPTION,
            schema: ['type' => 'string', 'example' => self::EXAMPLE_TIMEZONE],
          ),
        ),
        'nonConformityStatus' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string', 'enum' => OrganizationDashboardOpenApiValues::NON_CONFORMITY_STATUSES, 'example' => 'open'],
          description: self::NON_CONFORMITY_STATUS_FILTER_DESCRIPTION,
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(
            name: 'nonConformityStatus',
            in: 'query',
            required: false,
            description: self::NON_CONFORMITY_STATUS_FILTER_DESCRIPTION,
            schema: ['type' => 'string', 'enum' => OrganizationDashboardOpenApiValues::NON_CONFORMITY_STATUSES, 'example' => 'open'],
          ),
        ),
        'nonConformitySeverity' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string', 'enum' => OrganizationDashboardOpenApiValues::NON_CONFORMITY_SEVERITIES, 'example' => 'critical'],
          description: self::NON_CONFORMITY_SEVERITY_FILTER_DESCRIPTION,
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(
            name: 'nonConformitySeverity',
            in: 'query',
            required: false,
            description: self::NON_CONFORMITY_SEVERITY_FILTER_DESCRIPTION,
            schema: ['type' => 'string', 'enum' => OrganizationDashboardOpenApiValues::NON_CONFORMITY_SEVERITIES, 'example' => 'critical'],
          ),
        ),
        'metrics' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string', 'example' => 'non_conformities_opened,non_conformities_resolved'],
          description: self::METRICS_FILTER_DESCRIPTION,
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(
            name: 'metrics',
            in: 'query',
            required: false,
            description: self::METRICS_FILTER_DESCRIPTION,
            schema: ['type' => 'string', 'example' => 'non_conformities_opened,non_conformities_resolved'],
          ),
        ),
      ],
      openapi: new Operation(
        tags: ['Organization'],
        summary: 'Get non-conformities resolved trend',
        description: 'Returns the non-conformities-resolved trend as a single chart-ready series. Use this endpoint when a chart needs its own independent period or granularity, separate from the aggregate `/dashboard` payload. Access requires `organization.inspection.read`.',
        parameters: [],
      ),
    ),
  ],
)]
final class OrganizationDashboardTrendResource
{
  // #region Constants
  private const string SECURITY_ROLE_USER = "is_granted('ROLE_USER')";

  private const string EXAMPLE_FROM_DATE = '2026-03-01T00:00:00Z';

  private const string FROM_FILTER_DESCRIPTION = 'Inclusive ISO 8601 datetime lower bound for the trend period, with an explicit timezone offset. Optional microseconds are preserved.';

  private const string EXAMPLE_TO_DATE = '2026-03-29T23:59:59Z';

  private const string TO_FILTER_DESCRIPTION = 'Inclusive ISO 8601 datetime upper bound for the trend period, with an explicit timezone offset. Optional microseconds are preserved.';

  private const string COMPARE_FILTER_DESCRIPTION = 'Whether to include previous-period comparison series. Defaults to true.';

  private const string GRANULARITY_FILTER_DESCRIPTION = 'Trend aggregation granularity. Allowed values: day, week, month, auto. Defaults to day.';

  private const string EXAMPLE_TIMEZONE = 'Europe/Paris';

  private const string TIMEZONE_FILTER_DESCRIPTION = 'IANA timezone used for bucket boundaries and rendered period values. Required when the requested period spans DST, mixes offsets, or uses non-UTC numeric offsets.';

  private const string NON_CONFORMITY_STATUS_FILTER_DESCRIPTION = 'Optional non-conformity status filter applied to the non-conformity trend only.';

  private const string NON_CONFORMITY_SEVERITY_FILTER_DESCRIPTION = 'Optional non-conformity severity filter applied to the non-conformity trend only.';

  private const string METRICS_FILTER_DESCRIPTION = 'Optional comma-separated list of additional non-conformity metric identifiers to combine into `seriesByMetric`, sharing this call\'s resolved period, timezone and granularity so a two-series (opened vs resolved) chart can render from one request instead of two independently-bucketed calls. Allowed values: non_conformities_opened, non_conformities_resolved.';
  // #endregion
}
