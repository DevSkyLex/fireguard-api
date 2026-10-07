<?php

declare(strict_types=1);

namespace Maintenance\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Delete, Get, GetCollection, Patch, Post, QueryParameter};
use ApiPlatform\OpenApi\Model\Operation;
use Maintenance\Presentation\Api\Dto\Input\{ChangeMaintenancePlanInput, GenerateMaintenancePlanInput};
use Maintenance\Presentation\Api\Dto\Output\{GenerateMaintenancePlanOutput, MaintenancePlanEngineOutput, MaintenancePlanOutput, MaintenancePlanPreviewOutput};
use Maintenance\Presentation\Api\Operation\MaintenancePlanOperations;
use Maintenance\Presentation\Api\Processor\MaintenancePlanProcessor;
use Maintenance\Presentation\Api\Provider\MaintenancePlanProvider;

/** Organization-scoped operations retain immutable occurrence and legacy identities. */
#[ApiResource(
  shortName: 'MaintenancePlan',
  security: "is_granted('ROLE_USER')",
  normalizationContext: ['groups' => ['maintenance_plan:read']],
  denormalizationContext: ['groups' => ['maintenance_plan:write']],
  operations: [
    new GetCollection(name: MaintenancePlanOperations::LIST, uriTemplate: '/organizations/{organizationId}/maintenance/plans', output: MaintenancePlanOutput::class, provider: MaintenancePlanProvider::class, paginationEnabled: true, paginationClientItemsPerPage: true, paginationItemsPerPage: 30, paginationMaximumItemsPerPage: 100, parameters: [
      'equipmentId' => new QueryParameter(schema: ['type' => 'string', 'format' => 'uuid'], castToArray: false),
      'operationKind' => new QueryParameter(schema: ['type' => 'string', 'enum' => ['control', 'maintenance']], castToArray: false),
      'search' => new QueryParameter(schema: ['type' => 'string', 'maxLength' => 160], castToArray: false),
    ], openapi: new Operation(tags: ['Maintenance'], summary: 'List independent equipment operations')),
    new Get(name: MaintenancePlanOperations::ENGINE, uriTemplate: '/organizations/{organizationId}/maintenance/engine', output: MaintenancePlanEngineOutput::class, provider: MaintenancePlanProvider::class, openapi: new Operation(tags: ['Maintenance'], summary: 'Read active preventive engine')),
    new Post(name: MaintenancePlanOperations::PREPARE, uriTemplate: '/organizations/{organizationId}/maintenance/plans/prepare-legacy', input: false, output: MaintenancePlanEngineOutput::class, read: false, status: 200, processor: MaintenancePlanProcessor::class, openapi: new Operation(tags: ['Maintenance'], summary: 'Prepare historical plans without generating work')),
    new Post(name: MaintenancePlanOperations::ACTIVATE, uriTemplate: '/organizations/{organizationId}/maintenance/plans/activate', input: false, output: MaintenancePlanEngineOutput::class, read: false, status: 200, processor: MaintenancePlanProcessor::class, openapi: new Operation(tags: ['Maintenance'], summary: 'Atomically hand over the preventive engine')),
    new Post(name: MaintenancePlanOperations::CREATE, uriTemplate: '/organizations/{organizationId}/maintenance/plans', input: ChangeMaintenancePlanInput::class, output: MaintenancePlanOutput::class, read: false, status: 201, processor: MaintenancePlanProcessor::class, openapi: new Operation(tags: ['Maintenance'], summary: 'Prepare an equipment control or maintenance plan')),
    new Get(name: MaintenancePlanOperations::GET, uriTemplate: '/organizations/{organizationId}/maintenance/plans/{id}', requirements: ['id' => self::UUID_PATTERN], output: MaintenancePlanOutput::class, provider: MaintenancePlanProvider::class, openapi: new Operation(tags: ['Maintenance'], summary: 'Read an equipment operation')),
    new Patch(name: MaintenancePlanOperations::UPDATE, uriTemplate: '/organizations/{organizationId}/maintenance/plans/{id}', requirements: ['id' => self::UUID_PATTERN], input: ChangeMaintenancePlanInput::class, output: MaintenancePlanOutput::class, read: false, status: 200, processor: MaintenancePlanProcessor::class, openapi: new Operation(tags: ['Maintenance'], summary: 'Update an equipment operation')),
    new Delete(name: MaintenancePlanOperations::ARCHIVE, uriTemplate: '/organizations/{organizationId}/maintenance/plans/{id}', requirements: ['id' => self::UUID_PATTERN], input: false, output: false, read: false, status: 204, processor: MaintenancePlanProcessor::class, openapi: new Operation(tags: ['Maintenance'], summary: 'Archive an operation, retaining history')),
    new Get(name: MaintenancePlanOperations::PREVIEW, uriTemplate: '/organizations/{organizationId}/maintenance/plans/{id}/preview', requirements: ['id' => self::UUID_PATTERN], output: MaintenancePlanPreviewOutput::class, provider: MaintenancePlanProvider::class, openapi: new Operation(tags: ['Maintenance'], summary: 'Preview three dates before activation')),
    new Post(name: MaintenancePlanOperations::GENERATE, uriTemplate: '/organizations/{organizationId}/maintenance/plans/{id}/generate', requirements: ['id' => self::UUID_PATTERN], input: GenerateMaintenancePlanInput::class, output: GenerateMaintenancePlanOutput::class, read: false, status: 200, processor: MaintenancePlanProcessor::class, openapi: new Operation(tags: ['Maintenance'], summary: 'Reserve an occurrence and generate or replay its work')),
  ],
)]
final class MaintenancePlanResource
{
  private const string UUID_PATTERN = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}';
}
