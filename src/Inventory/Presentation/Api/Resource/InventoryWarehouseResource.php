<?php

declare(strict_types=1);

namespace Inventory\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get, GetCollection, Patch, Post};
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use Inventory\Presentation\Api\Dto\Input\{CreateInventoryWarehouseInput, PatchInventoryWarehouseInput};
use Inventory\Presentation\Api\Dto\Output\InventoryWarehouseOutput;
use Inventory\Presentation\Api\Operation\InventoryOperations;
use Inventory\Presentation\Api\Processor\InventoryProcessor;
use Inventory\Presentation\Api\Provider\InventoryProvider;

/** Manage warehouses. @category Resource */
#[ApiResource(shortName:'InventoryWarehouse', routePrefix:'/organizations', operations:[
  new GetCollection(name:InventoryOperations::WAREHOUSES_LIST, uriTemplate:'/{organizationId}/inventory-warehouses', input:false, output:InventoryWarehouseOutput::class, provider:InventoryProvider::class, paginationEnabled:true, paginationClientItemsPerPage:true, security:"is_granted('ROLE_USER')", normalizationContext:['groups' => ['inventory:read'], 'skip_null_values' => true], parameters:['search' => new QueryParameter(schema:['type' => 'string']), 'archived' => new QueryParameter(schema:['type' => 'string', 'enum' => ['true', 'false']])], openapi:new Operation(tags:['Inventory'], summary:'Manage warehouses')),
  new Get(name:InventoryOperations::WAREHOUSES_GET, uriTemplate:'/{organizationId}/inventory-warehouses/{id}', input:false, output:InventoryWarehouseOutput::class, provider:InventoryProvider::class, security:"is_granted('ROLE_USER')", normalizationContext:['groups' => ['inventory:read'], 'skip_null_values' => true]),
  new Post(name:InventoryOperations::WAREHOUSES_CREATE, uriTemplate:'/{organizationId}/inventory-warehouses', read:false, input:CreateInventoryWarehouseInput::class, output:InventoryWarehouseOutput::class, processor:InventoryProcessor::class, security:"is_granted('ROLE_USER')", normalizationContext:['groups' => ['inventory:read'], 'skip_null_values' => true], denormalizationContext:['groups' => ['inventory:write'], 'allow_extra_attributes' => false]),
  new Patch(name:InventoryOperations::WAREHOUSES_PATCH, uriTemplate:'/{organizationId}/inventory-warehouses/{id}', read:false, input:PatchInventoryWarehouseInput::class, output:InventoryWarehouseOutput::class, processor:InventoryProcessor::class, security:"is_granted('ROLE_USER')", normalizationContext:['groups' => ['inventory:read'], 'skip_null_values' => true], denormalizationContext:['groups' => ['inventory:write'], 'allow_extra_attributes' => false]),
])]
final class InventoryWarehouseResource
{
}
