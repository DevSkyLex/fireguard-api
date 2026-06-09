<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, GetCollection};
use ApiPlatform\OpenApi\Model\Operation;
use Equipment\Presentation\Api\Dto\Output\Equipment\EquipmentStatusOptionOutput;
use Equipment\Presentation\Api\Operation\EquipmentOperations;
use Equipment\Presentation\Api\Provider\Equipment\ListEquipmentStatusesProvider;
use Equipment\Presentation\Api\Serialization\EquipmentSerializationGroup;

#[ApiResource(
  shortName: 'EquipmentStatus',
  routePrefix: '/organizations',
  description: 'Equipment status catalog (read-only).',
  operations: [
    new GetCollection(
      name: EquipmentOperations::LIST_EQUIPMENT_STATUSES,
      uriTemplate: '/{organizationId}/equipment-statuses',
      input: false,
      output: EquipmentStatusOptionOutput::class,
      provider: ListEquipmentStatusesProvider::class,
      normalizationContext: ['groups' => [EquipmentSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      cacheHeaders: ['max_age' => 3600, 'shared_max_age' => 0],
      openapi: new Operation(
        tags: ['Equipment'],
        summary: 'List equipment statuses',
        description: 'Lists all supported equipment statuses with their human-readable labels.',
      ),
    ),
  ],
)]
final class EquipmentStatusResource
{
}
