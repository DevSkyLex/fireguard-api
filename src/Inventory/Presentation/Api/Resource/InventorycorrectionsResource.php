<?php

declare(strict_types=1);

namespace Inventory\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource,Post};
use Inventory\Presentation\Api\Dto\Input\CorrectInventoryStockInput;
use Inventory\Presentation\Api\Dto\Output\InventoryMovementOutput;
use Inventory\Presentation\Api\Operation\InventoryOperations;
use Inventory\Presentation\Api\Processor\InventoryProcessor;

/** Motivated stock corrections. @category Resource */
#[ApiResource(shortName:'Inventorycorrections', routePrefix:'/organizations', operations:[new Post(name:InventoryOperations::CORRECTIONS_CREATE, uriTemplate:'/{organizationId}/inventory-corrections', read:false, input:CorrectInventoryStockInput::class, output:InventoryMovementOutput::class, processor:InventoryProcessor::class, security:"is_granted('ROLE_USER')", normalizationContext:['groups' => ['inventory:read'], 'skip_null_values' => true], denormalizationContext:['groups' => ['inventory:write'], 'allow_extra_attributes' => false])])]
final class InventorycorrectionsResource
{
}
