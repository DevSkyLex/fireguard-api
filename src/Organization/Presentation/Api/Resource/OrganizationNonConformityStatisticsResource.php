<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get};
use ApiPlatform\OpenApi\Model\Operation;
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationNonConformityStatisticsOutput;
use Organization\Presentation\Api\Operation\OrganizationOperations;
use Organization\Presentation\Api\Provider\Organization\GetOrganizationNonConformityStatisticsProvider;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;

/**
 * Resource OrganizationNonConformityStatisticsResource.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'OrganizationNonConformityStatistics',
  routePrefix: '/organizations',
  description: 'Detailed non-conformity statistics for one organization dashboard widget.',
  operations: [
    new Get(
      name: OrganizationOperations::GET_ORGANIZATION_NON_CONFORMITY_STATISTICS,
      uriTemplate: '/{organizationId}/statistics/non-conformities',
      input: false,
      output: OrganizationNonConformityStatisticsOutput::class,
      provider: GetOrganizationNonConformityStatisticsProvider::class,
      normalizationContext: ['groups' => [OrganizationSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Organization'],
        summary: 'Get organization non-conformity statistics',
        description: 'Returns detailed non-conformity KPI counts for one organization dashboard widget.',
      ),
    ),
  ],
)]
final class OrganizationNonConformityStatisticsResource
{
}
