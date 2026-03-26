<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get};
use ApiPlatform\OpenApi\Model\Operation;
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationEquipmentStatisticsOutput;
use Organization\Presentation\Api\Operation\OrganizationOperations;
use Organization\Presentation\Api\Provider\Organization\GetOrganizationEquipmentStatisticsProvider;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;

/**
 * Resource OrganizationEquipmentStatisticsResource.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'OrganizationEquipmentStatistics',
  routePrefix: '/organizations',
  description: 'Detailed equipment statistics for one organization dashboard widget.',
  operations: [
    new Get(
      name: OrganizationOperations::GET_ORGANIZATION_EQUIPMENT_STATISTICS,
      uriTemplate: '/{organizationId}/statistics/equipment',
      input: false,
      output: OrganizationEquipmentStatisticsOutput::class,
      provider: GetOrganizationEquipmentStatisticsProvider::class,
      normalizationContext: ['groups' => [OrganizationSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Organization'],
        summary: 'Get organization equipment statistics',
        description: 'Returns detailed equipment KPI counts for one organization dashboard widget.',
      ),
    ),
  ],
)]
final class OrganizationEquipmentStatisticsResource
{
}
