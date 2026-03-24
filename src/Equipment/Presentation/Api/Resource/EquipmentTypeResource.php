<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, GetCollection};
use ApiPlatform\OpenApi\Model\Operation;
use Equipment\Presentation\Api\Dto\Output\Equipment\EquipmentTypeOutput;
use Equipment\Presentation\Api\Operation\EquipmentOperations;
use Equipment\Presentation\Api\Provider\Equipment\ListEquipmentTypesProvider;
use Equipment\Presentation\Api\Serialization\EquipmentSerializationGroup;

/**
 * Resource EquipmentTypeResource.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'EquipmentType',
  routePrefix: '/organizations',
  description: 'Equipment type catalog (read-only).',
  operations: [
    new GetCollection(
      name: EquipmentOperations::LIST_EQUIPMENT_TYPES,
      uriTemplate: '/{organizationId}/equipment-types',
      input: false,
      output: EquipmentTypeOutput::class,
      provider: ListEquipmentTypesProvider::class,
      normalizationContext: ['groups' => [EquipmentSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Equipment'],
        summary: 'List equipment types',
        description: 'Lists all supported equipment types with their human-readable labels.',
      ),
    ),
  ],
)]
final class EquipmentTypeResource
{
}
