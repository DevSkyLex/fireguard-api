<?php

declare(strict_types=1);

namespace Inventory\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get, GetCollection, Patch, Post};
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use Inventory\Presentation\Api\Dto\Input\{CreateInventoryPartInput, PatchInventoryPartInput};
use Inventory\Presentation\Api\Dto\Output\InventoryPartOutput;
use Inventory\Presentation\Api\Operation\InventoryOperations;
use Inventory\Presentation\Api\Processor\InventoryProcessor;
use Inventory\Presentation\Api\Provider\InventoryProvider;

/** Manage quantitative part and consumable references. @category Resource */
#[ApiResource(shortName:'InventoryPart', routePrefix:'/organizations', operations:[
  new GetCollection(name:InventoryOperations::PARTS_LIST, uriTemplate:'/{organizationId}/inventory-parts', input:false, output:InventoryPartOutput::class, provider:InventoryProvider::class, paginationEnabled:true, paginationClientItemsPerPage:true, security:"is_granted('ROLE_USER')", normalizationContext:['groups' => ['inventory:read'], 'skip_null_values' => true], parameters:['search' => new QueryParameter(schema:['type' => 'string']), 'archived' => new QueryParameter(schema:['type' => 'string', 'enum' => ['true', 'false']])], openapi:new Operation(tags:['Inventory'], summary:'Manage quantitative part and consumable references')),
  new Get(name:InventoryOperations::PARTS_GET, uriTemplate:'/{organizationId}/inventory-parts/{id}', input:false, output:InventoryPartOutput::class, provider:InventoryProvider::class, security:"is_granted('ROLE_USER')", normalizationContext:['groups' => ['inventory:read'], 'skip_null_values' => true]),
  new Post(name:InventoryOperations::PARTS_CREATE, uriTemplate:'/{organizationId}/inventory-parts', read:false, input:CreateInventoryPartInput::class, output:InventoryPartOutput::class, processor:InventoryProcessor::class, security:"is_granted('ROLE_USER')", normalizationContext:['groups' => ['inventory:read'], 'skip_null_values' => true], denormalizationContext:['groups' => ['inventory:write'], 'allow_extra_attributes' => false]),
  new Patch(name:InventoryOperations::PARTS_PATCH, uriTemplate:'/{organizationId}/inventory-parts/{id}', read:false, input:PatchInventoryPartInput::class, output:InventoryPartOutput::class, processor:InventoryProcessor::class, security:"is_granted('ROLE_USER')", normalizationContext:['groups' => ['inventory:read'], 'skip_null_values' => true], denormalizationContext:['groups' => ['inventory:write'], 'allow_extra_attributes' => false]),
])]
final class InventoryPartResource
{
}
