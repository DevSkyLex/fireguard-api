<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get, GetCollection, Patch, Post, Put};
use ApiPlatform\OpenApi\Model\{Operation, Parameter, Response};
use Equipment\Presentation\Api\Dto\Input\Equipment\{
  AssignToFacilityInput,
  CreateEquipmentInput,
  SetEquipmentPlanPositionInput,
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
  SetEquipmentPlanPositionProcessor,
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
  shortName: 'LegacyEquipment',
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
      security: self::SECURITY_ROLE_USER,
      openapi: new Operation(
        tags: ['Equipment'],
        summary: 'Create equipment',
        description: 'Creates a new equipment item in the organization stock.',
        responses: [
          HttpResponse::HTTP_CREATED => new Response(description: 'Equipment created'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: self::INVALID_INPUT_DESCRIPTION),
          HttpResponse::HTTP_CONFLICT => new Response(description: 'Serial number already exists in this organization'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: self::FORBIDDEN_DESCRIPTION),
        ],
      ),
    ),
    new GetCollection(
      name: EquipmentOperations::LIST_EQUIPMENTS,
      uriTemplate: '/{organizationId}/equipment',
      input: false,
      output: EquipmentOutput::class,
      provider: ListEquipmentsProvider::class,
      paginationEnabled: true,
      paginationClientItemsPerPage: true,
      // 200 rather than the 100 every other collection caps at: the facility overview previews a site's equipment in one page,
      // and a lower ceiling would silently drop rows past the cut rather than fail.
      paginationMaximumItemsPerPage: 200,
      paginationItemsPerPage: 30,
      normalizationContext: ['groups' => [EquipmentSerializationGroup::READ]],
      security: self::SECURITY_ROLE_USER,
      parameters: [
        'facilityId' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string', 'format' => 'uuid'],
          description: 'Filter by facility identifier.',
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(
            name: 'facilityId',
            in: 'query',
            required: false,
            description: 'Filter by facility identifier.',
            schema: ['type' => 'string', 'format' => 'uuid'],
          ),
        ),
        'type' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string'],
          description: self::TYPE_FILTER_DESCRIPTION,
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(
            name: 'type',
            in: 'query',
            required: false,
            description: self::TYPE_FILTER_DESCRIPTION,
            schema: ['type' => 'string'],
          ),
        ),
        'status' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string'],
          description: self::STATUS_FILTER_DESCRIPTION,
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(
            name: 'status',
            in: 'query',
            required: false,
            description: self::STATUS_FILTER_DESCRIPTION,
            schema: ['type' => 'string'],
          ),
        ),
        'brand' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string'],
          description: self::BRAND_FILTER_DESCRIPTION,
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(
            name: 'brand',
            in: 'query',
            required: false,
            description: self::BRAND_FILTER_DESCRIPTION,
            schema: ['type' => 'string'],
          ),
        ),
        'model' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string'],
          description: self::MODEL_FILTER_DESCRIPTION,
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(
            name: 'model',
            in: 'query',
            required: false,
            description: self::MODEL_FILTER_DESCRIPTION,
            schema: ['type' => 'string'],
          ),
        ),
        'subType' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string'],
          description: self::SUBTYPE_FILTER_DESCRIPTION,
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(
            name: 'subType',
            in: 'query',
            required: false,
            description: self::SUBTYPE_FILTER_DESCRIPTION,
            schema: ['type' => 'string'],
          ),
        ),
        'maintenanceDueStatus' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string', 'enum' => ['unscheduled', 'up_to_date', 'due_soon', 'overdue']],
          description: self::MAINTENANCE_DUE_FILTER_DESCRIPTION,
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(
            name: 'maintenanceDueStatus',
            in: 'query',
            required: false,
            description: self::MAINTENANCE_DUE_FILTER_DESCRIPTION,
            schema: ['type' => 'string', 'enum' => ['unscheduled', 'up_to_date', 'due_soon', 'overdue']],
          ),
        ),
      ],
      openapi: new Operation(
        tags: ['Equipment'],
        summary: 'List equipment',
        description: 'Lists equipment items for the target organization.',
        parameters: [],
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Equipment list retrieved'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Invalid organization identifier'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: self::FORBIDDEN_DESCRIPTION),
        ],
      ),
    ),
    new GetCollection(
      name: EquipmentOperations::LIST_FACILITY_EQUIPMENTS,
      uriTemplate: '/{organizationId}/facilities/{facilityId}/equipment',
      input: false,
      output: EquipmentOutput::class,
      provider: ListEquipmentsProvider::class,
      paginationEnabled: true,
      paginationClientItemsPerPage: true,
      paginationMaximumItemsPerPage: 100,
      paginationItemsPerPage: 30,
      normalizationContext: ['groups' => [EquipmentSerializationGroup::READ]],
      security: self::SECURITY_ROLE_USER,
      parameters: [
        'type' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string'],
          description: self::TYPE_FILTER_DESCRIPTION,
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(
            name: 'type',
            in: 'query',
            required: false,
            description: self::TYPE_FILTER_DESCRIPTION,
            schema: ['type' => 'string'],
          ),
        ),
        'status' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string'],
          description: self::STATUS_FILTER_DESCRIPTION,
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(
            name: 'status',
            in: 'query',
            required: false,
            description: self::STATUS_FILTER_DESCRIPTION,
            schema: ['type' => 'string'],
          ),
        ),
        'brand' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string'],
          description: self::BRAND_FILTER_DESCRIPTION,
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(
            name: 'brand',
            in: 'query',
            required: false,
            description: self::BRAND_FILTER_DESCRIPTION,
            schema: ['type' => 'string'],
          ),
        ),
        'model' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string'],
          description: self::MODEL_FILTER_DESCRIPTION,
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(
            name: 'model',
            in: 'query',
            required: false,
            description: self::MODEL_FILTER_DESCRIPTION,
            schema: ['type' => 'string'],
          ),
        ),
        'subType' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string'],
          description: self::SUBTYPE_FILTER_DESCRIPTION,
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(
            name: 'subType',
            in: 'query',
            required: false,
            description: self::SUBTYPE_FILTER_DESCRIPTION,
            schema: ['type' => 'string'],
          ),
        ),
        'maintenanceDueStatus' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string', 'enum' => ['unscheduled', 'up_to_date', 'due_soon', 'overdue']],
          description: self::MAINTENANCE_DUE_FILTER_DESCRIPTION,
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(
            name: 'maintenanceDueStatus',
            in: 'query',
            required: false,
            description: self::MAINTENANCE_DUE_FILTER_DESCRIPTION,
            schema: ['type' => 'string', 'enum' => ['unscheduled', 'up_to_date', 'due_soon', 'overdue']],
          ),
        ),
      ],
      openapi: new Operation(
        tags: ['Equipment'],
        summary: 'List facility equipment',
        description: 'Lists equipment items directly assigned to one facility.',
        parameters: [],
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Facility equipment list retrieved'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Invalid organization or facility identifier'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: self::FORBIDDEN_DESCRIPTION),
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
      security: self::SECURITY_ROLE_USER,
      openapi: new Operation(
        tags: ['Equipment'],
        summary: 'Get equipment',
        description: 'Returns one equipment item by identifier.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Equipment retrieved'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: self::INVALID_IDENTIFIER_DESCRIPTION),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: self::FORBIDDEN_DESCRIPTION),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: self::NOT_FOUND_DESCRIPTION),
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
      security: self::SECURITY_ROLE_USER,
      openapi: new Operation(
        tags: ['Equipment'],
        summary: 'Patch equipment',
        description: 'Partially updates equipment information.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Equipment updated'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: self::INVALID_INPUT_DESCRIPTION),
          HttpResponse::HTTP_CONFLICT => new Response(description: 'Serial number already exists in this organization'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: self::FORBIDDEN_DESCRIPTION),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: self::NOT_FOUND_DESCRIPTION),
        ],
      ),
    ),
    new Post(
      name: EquipmentOperations::ASSIGN_TO_FACILITY,
      uriTemplate: '/{organizationId}/equipment/{equipmentId}/assign',
      status: HttpResponse::HTTP_OK,
      input: AssignToFacilityInput::class,
      output: EquipmentOutput::class,
      processor: AssignToFacilityProcessor::class,
      denormalizationContext: ['groups' => [EquipmentSerializationGroup::WRITE]],
      normalizationContext: ['groups' => [EquipmentSerializationGroup::READ]],
      security: self::SECURITY_ROLE_USER,
      openapi: new Operation(
        tags: ['Equipment'],
        summary: 'Assign to facility',
        description: 'Assigns the equipment to a facility.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Equipment assigned'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: self::INVALID_INPUT_DESCRIPTION),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: self::FORBIDDEN_DESCRIPTION),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: self::NOT_FOUND_DESCRIPTION),
        ],
      ),
    ),
    new Post(
      name: EquipmentOperations::UNASSIGN_FROM_FACILITY,
      uriTemplate: '/{organizationId}/equipment/{equipmentId}/unassign',
      status: HttpResponse::HTTP_OK,
      input: false,
      output: EquipmentOutput::class,
      processor: UnassignFromFacilityProcessor::class,
      normalizationContext: ['groups' => [EquipmentSerializationGroup::READ]],
      security: self::SECURITY_ROLE_USER,
      openapi: new Operation(
        tags: ['Equipment'],
        summary: 'Unassign from facility',
        description: 'Removes the equipment from its current facility.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Equipment unassigned'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: self::INVALID_IDENTIFIER_DESCRIPTION),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: self::FORBIDDEN_DESCRIPTION),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: self::NOT_FOUND_DESCRIPTION),
        ],
      ),
    ),
    new Post(
      name: EquipmentOperations::COMMISSION_EQUIPMENT,
      uriTemplate: '/{organizationId}/equipment/{equipmentId}/commission',
      status: HttpResponse::HTTP_OK,
      input: false,
      output: EquipmentOutput::class,
      processor: CommissionEquipmentProcessor::class,
      normalizationContext: ['groups' => [EquipmentSerializationGroup::READ]],
      security: self::SECURITY_ROLE_USER,
      openapi: new Operation(
        tags: ['Equipment'],
        summary: 'Commission equipment',
        description: 'Marks the equipment as commissioned and operational.',
        deprecated: true,
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Equipment commissioned'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Invalid identifier or equipment not assigned to a facility'),
          HttpResponse::HTTP_CONFLICT => new Response(description: self::DECOMMISSIONED_DESCRIPTION),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: self::FORBIDDEN_DESCRIPTION),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: self::NOT_FOUND_DESCRIPTION),
        ],
      ),
    ),
    new Post(
      name: EquipmentOperations::PUT_UNDER_MAINTENANCE,
      uriTemplate: '/{organizationId}/equipment/{equipmentId}/maintenance',
      status: HttpResponse::HTTP_OK,
      input: false,
      output: EquipmentOutput::class,
      processor: PutUnderMaintenanceProcessor::class,
      normalizationContext: ['groups' => [EquipmentSerializationGroup::READ]],
      security: self::SECURITY_ROLE_USER,
      openapi: new Operation(
        tags: ['Equipment'],
        summary: 'Put under maintenance',
        description: 'Marks the equipment as under maintenance.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Equipment put under maintenance'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Invalid identifier or equipment not assigned to a facility'),
          HttpResponse::HTTP_CONFLICT => new Response(description: self::DECOMMISSIONED_DESCRIPTION),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: self::FORBIDDEN_DESCRIPTION),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: self::NOT_FOUND_DESCRIPTION),
        ],
      ),
    ),
    new Put(
      name: EquipmentOperations::SET_EQUIPMENT_PLAN_POSITION,
      uriTemplate: '/{organizationId}/equipment/{equipmentId}/plan-position',
      read: false,
      input: SetEquipmentPlanPositionInput::class,
      output: EquipmentOutput::class,
      processor: SetEquipmentPlanPositionProcessor::class,
      denormalizationContext: ['groups' => [EquipmentSerializationGroup::WRITE]],
      normalizationContext: ['groups' => [EquipmentSerializationGroup::READ]],
      security: self::SECURITY_ROLE_USER,
      openapi: new Operation(
        tags: ['Equipment'],
        summary: 'Set equipment plan position',
        description: 'Pins the equipment at a point over a floor plan attachment belonging to its own facility or one of its ancestors. Submit "attachmentId", "x" and "y" (normalized 0-1 coordinates) together to set or replace the position, or all three null to clear it. The equipment must be assigned to a facility.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Plan position set or cleared'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: self::INVALID_INPUT_DESCRIPTION),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: self::FORBIDDEN_DESCRIPTION),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Equipment or attachment not found'),
          HttpResponse::HTTP_CONFLICT => new Response(description: 'Equipment not assigned to a facility, already decommissioned, or attachment is not a floor plan / not an ancestor'),
        ],
      ),
    ),
    new Post(
      name: EquipmentOperations::DECOMMISSION_EQUIPMENT,
      uriTemplate: '/{organizationId}/equipment/{equipmentId}/decommission',
      status: HttpResponse::HTTP_OK,
      input: false,
      output: EquipmentOutput::class,
      processor: DecommissionEquipmentProcessor::class,
      normalizationContext: ['groups' => [EquipmentSerializationGroup::READ]],
      security: self::SECURITY_ROLE_USER,
      openapi: new Operation(
        tags: ['Equipment'],
        summary: 'Decommission equipment',
        description: 'Permanently decommissions the equipment.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Equipment decommissioned'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: self::INVALID_IDENTIFIER_DESCRIPTION),
          HttpResponse::HTTP_CONFLICT => new Response(description: self::DECOMMISSIONED_DESCRIPTION),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: self::FORBIDDEN_DESCRIPTION),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: self::NOT_FOUND_DESCRIPTION),
        ],
      ),
    ),
  ],
)]
/**
 * Resource EquipmentResource.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class EquipmentResource
{
  // #region Constants
  private const string SECURITY_ROLE_USER = "is_granted('ROLE_USER')";

  private const string FORBIDDEN_DESCRIPTION = 'Insufficient permissions';

  private const string NOT_FOUND_DESCRIPTION = 'Equipment not found';

  private const string INVALID_INPUT_DESCRIPTION = 'Invalid input';

  private const string INVALID_IDENTIFIER_DESCRIPTION = 'Invalid identifier';

  private const string DECOMMISSIONED_DESCRIPTION = 'Equipment already decommissioned';

  private const string TYPE_FILTER_DESCRIPTION = 'Filter by equipment type.';

  private const string STATUS_FILTER_DESCRIPTION = 'Filter by equipment status.';

  private const string BRAND_FILTER_DESCRIPTION = 'Filter by exact equipment brand.';

  private const string MODEL_FILTER_DESCRIPTION = 'Filter by exact equipment model.';

  private const string SUBTYPE_FILTER_DESCRIPTION = 'Filter by exact equipment subtype.';

  private const string MAINTENANCE_DUE_FILTER_DESCRIPTION = 'Filter by cross-module maintenance due status (`unscheduled`, `up_to_date`, `due_soon`, `overdue`).';
  // #endregion
}
