<?php

declare(strict_types=1);

namespace Inventory\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource,Get,GetCollection,QueryParameter};
use ApiPlatform\OpenApi\Model\Operation;
use Inventory\Presentation\Api\Dto\Output\InventoryMovementOutput;
use Inventory\Presentation\Api\Operation\InventoryOperations;
use Inventory\Presentation\Api\Provider\InventoryProvider;

/** Read immutable motivated stock movements. @category Resource */
#[ApiResource(shortName:'InventoryMovement', routePrefix:'/organizations', operations:[
  new GetCollection(name:InventoryOperations::MOVEMENTS_LIST, uriTemplate:'/{organizationId}/inventory-movements', input:false, output:InventoryMovementOutput::class, provider:InventoryProvider::class, paginationEnabled:true, paginationClientItemsPerPage:true, security:"is_granted('ROLE_USER')", normalizationContext:['groups' => ['inventory:read'], 'skip_null_values' => true], parameters:['warehouseId' => new QueryParameter(schema:['type' => 'string']), 'partId' => new QueryParameter(schema:['type' => 'string']), 'interventionId' => new QueryParameter(schema:['type' => 'string'])], openapi:new Operation(tags:['Inventory'], summary:'Read immutable motivated stock movements')),
  new Get(name:InventoryOperations::MOVEMENTS_GET, uriTemplate:'/{organizationId}/inventory-movements/{id}', input:false, output:InventoryMovementOutput::class, provider:InventoryProvider::class, security:"is_granted('ROLE_USER')", normalizationContext:['groups' => ['inventory:read'], 'skip_null_values' => true]),
])]
final class InventoryMovementResource
{
}
