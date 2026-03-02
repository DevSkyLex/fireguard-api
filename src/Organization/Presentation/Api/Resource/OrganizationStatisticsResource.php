<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get};
use ApiPlatform\OpenApi\Model\Operation;
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationStatisticsOutput;
use Organization\Presentation\Api\Operation\OrganizationOperations;
use Organization\Presentation\Api\Provider\Organization\GetOrganizationStatisticsProvider;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;

/**
 * Resource OrganizationStatisticsResource.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'OrganizationStatistics',
  routePrefix: '/organizations',
  description: 'Organization dashboard statistics.',
  operations: [
    new Get(
      name: OrganizationOperations::GET_ORGANIZATION_STATISTICS,
      uriTemplate: '/{organizationId}/statistics',
      input: false,
      output: OrganizationStatisticsOutput::class,
      provider: GetOrganizationStatisticsProvider::class,
      normalizationContext: ['groups' => [OrganizationSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Organization'],
        summary: 'Get Organization statistics',
        description: 'Returns aggregated statistics for an organization (member count, role count, facility count, pending invitations).',
      ),
    ),
  ],
)]
final class OrganizationStatisticsResource
{
}
