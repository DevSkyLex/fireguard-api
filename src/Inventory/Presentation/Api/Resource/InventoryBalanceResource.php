<?php

declare(strict_types=1);

namespace Inventory\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource,GetCollection,QueryParameter};
use ApiPlatform\OpenApi\Model\Operation;
use Inventory\Presentation\Api\Dto\Output\InventoryBalanceOutput;
use Inventory\Presentation\Api\Operation\InventoryOperations;
use Inventory\Presentation\Api\Provider\InventoryProvider;

/** Read nonnegative stock balances. @category Resource */
#[ApiResource(shortName:'InventoryBalance', routePrefix:'/organizations', operations:[
  new GetCollection(name:InventoryOperations::BALANCES_LIST, uriTemplate:'/{organizationId}/inventory-balances', input:false, output:InventoryBalanceOutput::class, provider:InventoryProvider::class, paginationEnabled:true, paginationClientItemsPerPage:true, security:"is_granted('ROLE_USER')", normalizationContext:['groups' => ['inventory:read'], 'skip_null_values' => true], parameters:['warehouseId' => new QueryParameter(schema:['type' => 'string']), 'partId' => new QueryParameter(schema:['type' => 'string'])], openapi:new Operation(tags:['Inventory'], summary:'Read nonnegative stock balances')),
])]
final class InventoryBalanceResource
{
}
