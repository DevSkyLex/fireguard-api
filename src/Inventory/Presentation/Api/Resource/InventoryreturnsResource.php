<?php

declare(strict_types=1);

namespace Inventory\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource,Post};
use Inventory\Presentation\Api\Dto\Input\ReturnInventoryConsumptionInput;
use Inventory\Presentation\Api\Dto\Output\InventoryMovementOutput;
use Inventory\Presentation\Api\Operation\InventoryOperations;
use Inventory\Presentation\Api\Processor\InventoryProcessor;

/** Motivated stock returns. @category Resource */
#[ApiResource(shortName:'Inventoryreturns', routePrefix:'/organizations', operations:[new Post(name:InventoryOperations::RETURNS_CREATE, uriTemplate:'/{organizationId}/inventory-returns', read:false, input:ReturnInventoryConsumptionInput::class, output:InventoryMovementOutput::class, processor:InventoryProcessor::class, security:"is_granted('ROLE_USER')", normalizationContext:['groups' => ['inventory:read'], 'skip_null_values' => true], denormalizationContext:['groups' => ['inventory:write'], 'allow_extra_attributes' => false])])]
final class InventoryreturnsResource
{
}
