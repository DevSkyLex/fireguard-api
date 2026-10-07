<?php

declare(strict_types=1);

namespace MaintenanceCost\Presentation\Api\Resource\Rate;

use ApiPlatform\Metadata\{ApiResource, GetCollection, Post, QueryParameter};
use ApiPlatform\OpenApi\Model\Operation;
use MaintenanceCost\Presentation\Api\Dto\Input\Rate\CreateMaintenanceRateInput;
use MaintenanceCost\Presentation\Api\Dto\Output\Rate\MaintenanceRateOutput;
use MaintenanceCost\Presentation\Api\Operation\Rate\MaintenanceRateOperations;
use MaintenanceCost\Presentation\Api\Processor\Rate\CreateMaintenanceRateProcessor;
use MaintenanceCost\Presentation\Api\Provider\Rate\MaintenanceRateProvider;

/** Dedicated financial permissions protect append-only historical rate parameters. */
#[ApiResource(
  shortName: 'MaintenanceCostRate',
  security: "is_granted('ROLE_USER')",
  normalizationContext: ['groups' => ['maintenance_cost_rate:read']],
  denormalizationContext: ['groups' => ['maintenance_cost_rate:write'], 'allow_extra_attributes' => false],
  operations: [
    new GetCollection(name: MaintenanceRateOperations::LIST, uriTemplate: '/organizations/{organizationId}/maintenance-cost/rates', output: MaintenanceRateOutput::class, provider: MaintenanceRateProvider::class, paginationEnabled: true, paginationClientItemsPerPage: true, paginationItemsPerPage: 30, paginationMaximumItemsPerPage: 100, parameters: [
      'memberId' => new QueryParameter(schema: ['type' => 'string', 'format' => 'uuid'], castToArray: false),
    ], openapi: new Operation(tags: ['MaintenanceCost'], summary: 'List private exact hourly rates')),
    new Post(name: MaintenanceRateOperations::CREATE, uriTemplate: '/organizations/{organizationId}/maintenance-cost/rates', input: CreateMaintenanceRateInput::class, output: MaintenanceRateOutput::class, read: false, status: 201, processor: CreateMaintenanceRateProcessor::class, openapi: new Operation(tags: ['MaintenanceCost'], summary: 'Append an effective hourly rate using a stable client identity')),
  ],
)]
final class MaintenanceRateResource
{
}
