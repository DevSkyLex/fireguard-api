<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get};
use ApiPlatform\OpenApi\Model\Operation;
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationInspectionStatisticsOutput;
use Organization\Presentation\Api\Operation\OrganizationOperations;
use Organization\Presentation\Api\Provider\Organization\GetOrganizationInspectionStatisticsProvider;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;

/**
 * Resource OrganizationInspectionStatisticsResource.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'OrganizationInspectionStatistics',
  routePrefix: '/organizations',
  description: 'Detailed inspection statistics for one organization dashboard widget.',
  operations: [
    new Get(
      name: OrganizationOperations::GET_ORGANIZATION_INSPECTION_STATISTICS,
      uriTemplate: '/{organizationId}/statistics/inspections',
      input: false,
      output: OrganizationInspectionStatisticsOutput::class,
      provider: GetOrganizationInspectionStatisticsProvider::class,
      normalizationContext: ['groups' => [OrganizationSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Organization'],
        summary: 'Get organization inspection statistics',
        description: 'Returns detailed inspection KPI counts and recent activity for one organization dashboard widget.',
      ),
    ),
  ],
)]
final class OrganizationInspectionStatisticsResource
{
}
