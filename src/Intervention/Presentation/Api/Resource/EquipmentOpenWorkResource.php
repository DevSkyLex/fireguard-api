<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, GetCollection};
use Intervention\Presentation\Api\Dto\Output\EquipmentOpenWorkOutput;
use Intervention\Presentation\Api\Operation\InterventionOperations;
use Intervention\Presentation\Api\Provider\EquipmentOpenWorkProvider;

/**
 * Class EquipmentOpenWorkResource
 *
 * Provides a scoped lookup without changing equipment-read permissions.
 *
 * @category Resource
 */
#[ApiResource(shortName: 'EquipmentOpenWork', operations: [new GetCollection(name: InterventionOperations::LIST_EQUIPMENT_OPEN_WORK, uriTemplate: '/organizations/{organizationId}/equipment/{equipmentId}/open-work', output: EquipmentOpenWorkOutput::class, provider: EquipmentOpenWorkProvider::class, security: "is_granted('ROLE_USER')", paginationEnabled: false)])]
final class EquipmentOpenWorkResource
{
}
