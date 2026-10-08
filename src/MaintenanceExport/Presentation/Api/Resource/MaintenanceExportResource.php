<?php

declare(strict_types=1);

namespace MaintenanceExport\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource,Get,GetCollection,Patch,Post,QueryParameter};
use ApiPlatform\OpenApi\Model\Operation;
use MaintenanceExport\Presentation\Api\Dto\Input\{AdjustMaintenanceExportInput,ConfirmMaintenanceExportInput,CreateMaintenanceExportInput,WriteMaintenanceExportReferenceInput};
use MaintenanceExport\Presentation\Api\Dto\Output\{MaintenanceExportOutput,MaintenanceExportReferenceOutput,MaintenanceExportSourceOutput};
use MaintenanceExport\Presentation\Api\Operation\MaintenanceExportOperations;
use MaintenanceExport\Presentation\Api\Processor\MaintenanceExportProcessor;
use MaintenanceExport\Presentation\Api\Provider\MaintenanceExportProvider;

/**
 * Class MaintenanceExportResource
 * Immutable saved files and explicit import acknowledgements keep ERP transport separate from publication.
 *
 * @category Resource
 */
#[ApiResource(
  shortName:'MaintenanceExport',
  security:"is_granted('ROLE_USER')",
  normalizationContext:['groups' => ['maintenance_export:read'], 'skip_null_values' => false],
  denormalizationContext:['groups' => ['maintenance_export:write']],
  operations:[
    new GetCollection(name:MaintenanceExportOperations::EXPORTS, uriTemplate:'/organizations/{organizationId}/maintenance-exports', output:MaintenanceExportOutput::class, provider:MaintenanceExportProvider::class, paginationEnabled:true, paginationClientItemsPerPage:true, paginationItemsPerPage:30, paginationMaximumItemsPerPage:100, parameters:['system' => new QueryParameter(schema:['type' => 'string'], castToArray:false)], openapi:new Operation(tags:[self::OPENAPI_TAG], summary:'List preserved ERP prestation exports')),
    new Post(name:MaintenanceExportOperations::CREATE, uriTemplate:'/organizations/{organizationId}/maintenance-exports', input:CreateMaintenanceExportInput::class, output:MaintenanceExportOutput::class, processor:MaintenanceExportProcessor::class, read:false, status:201, openapi:new Operation(tags:[self::OPENAPI_TAG], summary:'Preserve versioned CSV and JSON bytes atomically')),
    new Get(name:MaintenanceExportOperations::EXPORT, uriTemplate:'/organizations/{organizationId}/maintenance-exports/{id}', output:MaintenanceExportOutput::class, provider:MaintenanceExportProvider::class, openapi:new Operation(tags:[self::OPENAPI_TAG], summary:'Read retained export metadata')),
    new Post(name:MaintenanceExportOperations::ADJUST, uriTemplate:'/organizations/{organizationId}/maintenance-exports/{id}/adjustments', input:AdjustMaintenanceExportInput::class, output:MaintenanceExportOutput::class, processor:MaintenanceExportProcessor::class, read:false, status:201, openapi:new Operation(tags:[self::OPENAPI_TAG], summary:'Append an adjustment without rewriting original files')),
    new Post(name:MaintenanceExportOperations::CONFIRM, uriTemplate:'/organizations/{organizationId}/maintenance-exports/{id}/confirm', input:ConfirmMaintenanceExportInput::class, output:MaintenanceExportOutput::class, processor:MaintenanceExportProcessor::class, read:false, status:200, openapi:new Operation(tags:[self::OPENAPI_TAG], summary:'Explicitly acknowledge the external import')),
    new Get(name:MaintenanceExportOperations::FILE, uriTemplate:'/organizations/{organizationId}/maintenance-exports/{id}/files/{format}', output:false, provider:MaintenanceExportProvider::class, serialize:false, openapi:new Operation(tags:[self::OPENAPI_TAG], summary:'Download the exact preserved CSV or JSON bytes')),
    new GetCollection(name:MaintenanceExportOperations::SOURCES, uriTemplate:'/organizations/{organizationId}/maintenance-export-sources', output:MaintenanceExportSourceOutput::class, provider:MaintenanceExportProvider::class, paginationEnabled:true, paginationClientItemsPerPage:true, paginationItemsPerPage:30, paginationMaximumItemsPerPage:100, parameters:['search' => new QueryParameter(schema:['type' => 'string'], castToArray:false)], openapi:new Operation(tags:[self::OPENAPI_TAG], summary:'List published dossiers with explicit snapshot availability')),
    new GetCollection(name:MaintenanceExportOperations::REFERENCES, uriTemplate:'/organizations/{organizationId}/maintenance-export-references', output:MaintenanceExportReferenceOutput::class, provider:MaintenanceExportProvider::class, paginationEnabled:true, paginationClientItemsPerPage:true, paginationItemsPerPage:30, paginationMaximumItemsPerPage:100, parameters:['system' => new QueryParameter(schema:['type' => 'string'], castToArray:false), 'resourceType' => new QueryParameter(schema:['type' => 'string', 'enum' => ['customer', 'site', 'equipment']], castToArray:false), 'resourceId' => new QueryParameter(schema:['type' => 'string', 'format' => 'uuid'], castToArray:false)], openapi:new Operation(tags:[self::OPENAPI_TAG], summary:'Read scoped external identity mappings')),
    new Patch(name:MaintenanceExportOperations::WRITE_REFERENCE, uriTemplate:'/organizations/{organizationId}/maintenance-export-references/{resourceType}/{resourceId}', input:WriteMaintenanceExportReferenceInput::class, output:MaintenanceExportReferenceOutput::class, processor:MaintenanceExportProcessor::class, read:false, status:200, openapi:new Operation(tags:[self::OPENAPI_TAG], summary:'Set an external identity reference with an optimistic revision')),
  ],
)]
final class MaintenanceExportResource
{
  // #region Constants
  /**
   * Constant OPENAPI_TAG
   *
   * Groups the retained export and identity mapping operations in the API documentation.
   */
  private const string OPENAPI_TAG = 'Maintenance export';
  // #endregion
}
