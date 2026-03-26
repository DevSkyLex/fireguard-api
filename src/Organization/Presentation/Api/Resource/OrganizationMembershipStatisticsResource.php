<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get};
use ApiPlatform\OpenApi\Model\Operation;
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationMembershipStatisticsOutput;
use Organization\Presentation\Api\Operation\OrganizationOperations;
use Organization\Presentation\Api\Provider\Organization\GetOrganizationMembershipStatisticsProvider;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;

/**
 * Resource OrganizationMembershipStatisticsResource.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'OrganizationMembershipStatistics',
  routePrefix: '/organizations',
  description: 'Detailed membership statistics for one organization dashboard widget.',
  operations: [
    new Get(
      name: OrganizationOperations::GET_ORGANIZATION_MEMBERSHIP_STATISTICS,
      uriTemplate: '/{organizationId}/statistics/membership',
      input: false,
      output: OrganizationMembershipStatisticsOutput::class,
      provider: GetOrganizationMembershipStatisticsProvider::class,
      normalizationContext: ['groups' => [OrganizationSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Organization'],
        summary: 'Get organization membership statistics',
        description: 'Returns detailed member, role, and invitation KPI counts for one organization dashboard widget.',
      ),
    ),
  ],
)]
final class OrganizationMembershipStatisticsResource
{
}
