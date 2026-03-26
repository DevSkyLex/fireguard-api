<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get};
use ApiPlatform\OpenApi\Model\Operation;
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationFacilityStatisticsOutput;
use Organization\Presentation\Api\Operation\OrganizationOperations;
use Organization\Presentation\Api\Provider\Organization\GetOrganizationFacilityStatisticsProvider;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;

/**
 * Resource OrganizationFacilityStatisticsResource.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'OrganizationFacilityStatistics',
  routePrefix: '/organizations',
  description: 'Detailed facility statistics for one organization dashboard widget.',
  operations: [
    new Get(
      name: OrganizationOperations::GET_ORGANIZATION_FACILITY_STATISTICS,
      uriTemplate: '/{organizationId}/statistics/facilities',
      input: false,
      output: OrganizationFacilityStatisticsOutput::class,
      provider: GetOrganizationFacilityStatisticsProvider::class,
      normalizationContext: ['groups' => [OrganizationSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Organization'],
        summary: 'Get organization facility statistics',
        description: 'Returns detailed facility KPI counts for one organization dashboard widget.',
      ),
    ),
  ],
)]
final class OrganizationFacilityStatisticsResource
{
}
