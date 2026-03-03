<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get, GetCollection, Patch, Post};
use ApiPlatform\OpenApi\Model\{Operation, Parameter, Response};
use Equipment\Presentation\Api\Dto\Input\Equipment\{
  AssignToFacilityInput,
  CreateEquipmentInput,
  UpdateEquipmentInput
};
use Equipment\Presentation\Api\Dto\Output\Equipment\EquipmentOutput;
use Equipment\Presentation\Api\Operation\EquipmentOperations;
use Equipment\Presentation\Api\Processor\Equipment\{
  AssignToFacilityProcessor,
  CommissionEquipmentProcessor,
  CreateEquipmentProcessor,
  DecommissionEquipmentProcessor,
  PutUnderMaintenanceProcessor,
  UnassignFromFacilityProcessor,
  UpdateEquipmentProcessor
};
use Equipment\Presentation\Api\Provider\Equipment\{GetEquipmentProvider, ListEquipmentsProvider};
use Equipment\Presentation\Api\Serialization\EquipmentSerializationGroup;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Resource EquipmentResource.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'Equipment',
  routePrefix: '/organizations',
  description: 'Fire safety equipment and assets managed at organization level.',
  operations: [
    new Post(
      name: EquipmentOperations::CREATE_EQUIPMENT,
      uriTemplate: '/{organizationId}/equipment',
      input: CreateEquipmentInput::class,
      output: EquipmentOutput::class,
      processor: CreateEquipmentProcessor::class,
      denormalizationContext: ['groups' => [EquipmentSerializationGroup::WRITE]],
      normalizationContext: ['groups' => [EquipmentSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Equipment'],
        summary: 'Create equipment',
        description: 'Creates a new equipment item in the organization stock.',
        responses: [
          HttpResponse::HTTP_CREATED => new Response(description: 'Equipment created'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Invalid input'),
          HttpResponse::HTTP_CONFLICT => new Response(description: 'Serial number already exists in this organization'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
        ],
      ),
    ),
    new GetCollection(
      name: EquipmentOperations::LIST_EQUIPMENTS,
      uriTemplate: '/{organizationId}/equipment',
      input: false,
      output: EquipmentOutput::class,
      provider: ListEquipmentsProvider::class,
      normalizationContext: ['groups' => [EquipmentSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Equipment'],
        summary: 'List equipment',
        description: 'Lists equipment items for the target organization.',
        parameters: [
          new Parameter(
            name: 'facilityId',
            in: 'query',
            required: false,
            description: 'Filter by facility identifier.',
            schema: ['type' => 'string', 'format' => 'uuid'],
          ),
          new Parameter(
            name: 'type',
            in: 'query',
            required: false,
            description: 'Filter by equipment type.',
            schema: ['type' => 'string'],
          ),
          new Parameter(
            name: 'status',
            in: 'query',
            required: false,
            description: 'Filter by equipment status.',
            schema: ['type' => 'string'],
          ),
        ],
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Equipment list retrieved'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Invalid organization identifier'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
        ],
      ),
    ),
    new Get(
      name: EquipmentOperations::GET_EQUIPMENT,
      uriTemplate: '/{organizationId}/equipment/{equipmentId}',
      input: false,
      output: EquipmentOutput::class,
      provider: GetEquipmentProvider::class,
      normalizationContext: ['groups' => [EquipmentSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Equipment'],
        summary: 'Get equipment',
        description: 'Returns one equipment item by identifier.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Equipment retrieved'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Invalid identifier'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Equipment not found'),
        ],
      ),
    ),
    new Patch(
      name: EquipmentOperations::UPDATE_EQUIPMENT,
      uriTemplate: '/{organizationId}/equipment/{equipmentId}',
      read: false,
      input: UpdateEquipmentInput::class,
      output: EquipmentOutput::class,
      processor: UpdateEquipmentProcessor::class,
      denormalizationContext: ['groups' => [EquipmentSerializationGroup::WRITE]],
      normalizationContext: ['groups' => [EquipmentSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Equipment'],
        summary: 'Patch equipment',
        description: 'Partially updates equipment information.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Equipment updated'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Invalid input'),
          HttpResponse::HTTP_CONFLICT => new Response(description: 'Serial number already exists in this organization'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Equipment not found'),
        ],
      ),
    ),
    new Post(
      name: EquipmentOperations::ASSIGN_TO_FACILITY,
      uriTemplate: '/{organizationId}/equipment/{equipmentId}/assign',
      input: AssignToFacilityInput::class,
      output: EquipmentOutput::class,
      processor: AssignToFacilityProcessor::class,
      denormalizationContext: ['groups' => [EquipmentSerializationGroup::WRITE]],
      normalizationContext: ['groups' => [EquipmentSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Equipment'],
        summary: 'Assign to facility',
        description: 'Assigns the equipment to a facility.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Equipment assigned'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Invalid input'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Equipment not found'),
        ],
      ),
    ),
    new Post(
      name: EquipmentOperations::UNASSIGN_FROM_FACILITY,
      uriTemplate: '/{organizationId}/equipment/{equipmentId}/unassign',
      input: false,
      output: EquipmentOutput::class,
      processor: UnassignFromFacilityProcessor::class,
      normalizationContext: ['groups' => [EquipmentSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Equipment'],
        summary: 'Unassign from facility',
        description: 'Removes the equipment from its current facility.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Equipment unassigned'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Invalid identifier'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Equipment not found'),
        ],
      ),
    ),
    new Post(
      name: EquipmentOperations::COMMISSION_EQUIPMENT,
      uriTemplate: '/{organizationId}/equipment/{equipmentId}/commission',
      input: false,
      output: EquipmentOutput::class,
      processor: CommissionEquipmentProcessor::class,
      normalizationContext: ['groups' => [EquipmentSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Equipment'],
        summary: 'Commission equipment',
        description: 'Marks the equipment as commissioned and operational.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Equipment commissioned'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Invalid identifier'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Equipment not found'),
        ],
      ),
    ),
    new Post(
      name: EquipmentOperations::PUT_UNDER_MAINTENANCE,
      uriTemplate: '/{organizationId}/equipment/{equipmentId}/maintenance',
      input: false,
      output: EquipmentOutput::class,
      processor: PutUnderMaintenanceProcessor::class,
      normalizationContext: ['groups' => [EquipmentSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Equipment'],
        summary: 'Put under maintenance',
        description: 'Marks the equipment as under maintenance.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Equipment put under maintenance'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Invalid identifier'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Equipment not found'),
        ],
      ),
    ),
    new Post(
      name: EquipmentOperations::DECOMMISSION_EQUIPMENT,
      uriTemplate: '/{organizationId}/equipment/{equipmentId}/decommission',
      input: false,
      output: EquipmentOutput::class,
      processor: DecommissionEquipmentProcessor::class,
      normalizationContext: ['groups' => [EquipmentSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Equipment'],
        summary: 'Decommission equipment',
        description: 'Permanently decommissions the equipment.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Equipment decommissioned'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Invalid identifier'),
          HttpResponse::HTTP_CONFLICT => new Response(description: 'Equipment already decommissioned'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Equipment not found'),
        ],
      ),
    ),
  ],
)]
final class EquipmentResource
{
}
