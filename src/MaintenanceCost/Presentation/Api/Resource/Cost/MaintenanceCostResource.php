<?php

declare(strict_types=1);

namespace MaintenanceCost\Presentation\Api\Resource\Cost;

use ApiPlatform\Metadata\{ApiResource, Get, Patch, Post};
use ApiPlatform\OpenApi\Model\{Operation, Parameter, Response};
use MaintenanceCost\Presentation\Api\Dto\Input\Cost\{CreateMaintenanceExpenseInput, WriteCostPlanningInput};
use MaintenanceCost\Presentation\Api\Dto\Output\Cost\MaintenanceCostOutput;
use MaintenanceCost\Presentation\Api\Operation\Cost\MaintenanceCostOperations;
use MaintenanceCost\Presentation\Api\Processor\Cost\MaintenanceCostProcessor;
use MaintenanceCost\Presentation\Api\Provider\Cost\MaintenanceCostProvider;

/** Class MaintenanceCostResource. Financial permissions are enforced by application use cases. @category Resource */
#[ApiResource(
  shortName: 'MaintenanceCost',
  security: "is_granted('ROLE_USER')",
  normalizationContext: ['groups' => ['maintenance_cost:read']],
  denormalizationContext: ['groups' => ['maintenance_cost:write'], 'allow_extra_attributes' => false],
  operations: [
    new Get(name: MaintenanceCostOperations::READ, uriTemplate: '/organizations/{organizationId}/interventions/{id}/costs', output: MaintenanceCostOutput::class, provider: MaintenanceCostProvider::class, openapi: new Operation(tags: ['MaintenanceCost'], summary: 'Read private current and frozen intervention costs', responses: [200 => new Response(description: 'Private internal costs'), 403 => new Response(description: 'Financial read permission missing'), 404 => new Response(description: 'Intervention unavailable')])),
    new Patch(name: MaintenanceCostOperations::PLANNING, uriTemplate: '/organizations/{organizationId}/interventions/{id}/costs/planning', read: false, status: 200, input: WriteCostPlanningInput::class, output: MaintenanceCostOutput::class, processor: MaintenanceCostProcessor::class, openapi: new Operation(tags: ['MaintenanceCost'], summary: 'Prepare an internal budget and estimated resources', parameters: [new Parameter(name: 'If-Match', in: 'header', required: true, schema: ['type' => 'string', 'example' => '"revision-0"'])], responses: [200 => new Response(description: 'Financial preparation updated'), 409 => new Response(description: 'Preparation already closed'), 412 => new Response(description: 'Stale planning revision'), 428 => new Response(description: 'Planning revision required')])),
    new Post(name: MaintenanceCostOperations::EXPENSE, uriTemplate: '/organizations/{organizationId}/interventions/{id}/costs/expenses', read: false, status: 201, input: CreateMaintenanceExpenseInput::class, output: MaintenanceCostOutput::class, processor: MaintenanceCostProcessor::class, openapi: new Operation(tags: ['MaintenanceCost'], summary: 'Append a motivated external expense or correction', responses: [201 => new Response(description: 'Expense retained or exact replay'), 409 => new Response(description: 'Stable declaration identity conflict'), 422 => new Response(description: 'Invalid exact amount or source')])),
  ],
)]
final class MaintenanceCostResource
{
}
