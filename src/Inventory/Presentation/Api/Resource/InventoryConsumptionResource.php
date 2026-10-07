<?php

declare(strict_types=1);

namespace Inventory\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource,Get,GetCollection,Post,QueryParameter};
use ApiPlatform\OpenApi\Model\Operation;
use Inventory\Presentation\Api\Dto\Input\{DeclareInventoryConsumptionInput, ReconcileInventoryConsumptionInput};
use Inventory\Presentation\Api\Dto\Output\InventoryConsumptionOutput;
use Inventory\Presentation\Api\Operation\InventoryOperations;
use Inventory\Presentation\Api\Processor\InventoryProcessor;
use Inventory\Presentation\Api\Provider\InventoryProvider;

/** Declare complete physical consumptions, retaining shortages. @category Resource */
#[ApiResource(shortName:'InventoryConsumption', routePrefix:'/organizations', operations:[
  new GetCollection(name:InventoryOperations::CONSUMPTIONS_LIST, uriTemplate:'/{organizationId}/inventory-consumptions', input:false, output:InventoryConsumptionOutput::class, provider:InventoryProvider::class, paginationEnabled:true, paginationClientItemsPerPage:true, security:"is_granted('ROLE_USER')", normalizationContext:['groups' => ['inventory:read'], 'skip_null_values' => true], parameters:['warehouseId' => new QueryParameter(schema:['type' => 'string']), 'partId' => new QueryParameter(schema:['type' => 'string']), 'interventionId' => new QueryParameter(schema:['type' => 'string']), 'status' => new QueryParameter(schema:['type' => 'string'])], openapi:new Operation(tags:['Inventory'], summary:'Declare complete physical consumptions, retaining shortages')),
  new Get(name:InventoryOperations::CONSUMPTIONS_GET, uriTemplate:'/{organizationId}/inventory-consumptions/{id}', input:false, output:InventoryConsumptionOutput::class, provider:InventoryProvider::class, security:"is_granted('ROLE_USER')", normalizationContext:['groups' => ['inventory:read'], 'skip_null_values' => true]),
  new Post(name:InventoryOperations::CONSUMPTIONS_CREATE, uriTemplate:'/{organizationId}/inventory-consumptions', read:false, input:DeclareInventoryConsumptionInput::class, output:InventoryConsumptionOutput::class, processor:InventoryProcessor::class, security:"is_granted('ROLE_USER')", normalizationContext:['groups' => ['inventory:read'], 'skip_null_values' => true], denormalizationContext:['groups' => ['inventory:write'], 'allow_extra_attributes' => false]),
  new Post(name:InventoryOperations::CONSUMPTIONS_RECONCILE, uriTemplate:'/{organizationId}/inventory-consumptions/{id}/reconcile', read:false, status:200, input:ReconcileInventoryConsumptionInput::class, output:InventoryConsumptionOutput::class, processor:InventoryProcessor::class, security:"is_granted('ROLE_USER')", normalizationContext:['groups' => ['inventory:read'], 'skip_null_values' => true], denormalizationContext:['groups' => ['inventory:write'], 'allow_extra_attributes' => false]),
])]
final class InventoryConsumptionResource
{
}
