<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get, GetCollection, Patch, Post, Put};
use ApiPlatform\OpenApi\Model\{Operation, Parameter, Response};
use Facility\Application\UseCase\Query\ExportFacilities\ExportFacilitiesHandler;
use Facility\Application\UseCase\Query\GeocodeAddress\GeocodeAddressHandler;
use Facility\Presentation\Api\Controller\ExportFacilitiesController;
use Facility\Presentation\Api\Dto\Input\Facility\{
  CreateFacilityInput,
  DuplicateFacilitySubtreeInput,
  MoveFacilityInput,
  SetFacilityPlanGeometryInput,
  UpdateFacilityInput
};
use Facility\Presentation\Api\Dto\Output\Facility\{FacilityOutput, FacilityPlanOverlayOutput, GeocodeAddressOutput};
use Facility\Presentation\Api\Operation\FacilityOperations;
use Facility\Presentation\Api\Processor\Facility\{
  ArchiveFacilityProcessor,
  CreateFacilityProcessor,
  DuplicateFacilitySubtreeProcessor,
  MoveFacilityProcessor,
  RestoreFacilityProcessor,
  SetFacilityPlanGeometryProcessor,
  UpdateFacilityProcessor
};
use Facility\Presentation\Api\Provider\Facility\{
  FacilityPlanOverlayProvider,
  GeocodeAddressProvider,
  GetFacilityProvider,
  ListFacilitiesProvider,
  ListFacilityChildrenProvider,
  ListFacilityDescendantsProvider
};
use Facility\Presentation\Api\Serialization\FacilitySerializationGroup;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Resource FacilityResource.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'LegacyFacility',
  routePrefix: '/organizations',
  description: 'Generic organizational facilities (site/building/floor/zone/area).',
  operations: [
    new Post(
      name: FacilityOperations::CREATE_FACILITY,
      uriTemplate: '/{organizationId}/facilities',
      input: CreateFacilityInput::class,
      output: FacilityOutput::class,
      processor: CreateFacilityProcessor::class,
      denormalizationContext: ['groups' => [FacilitySerializationGroup::WRITE]],
      normalizationContext: ['groups' => [FacilitySerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Facility'],
        summary: 'Create facility',
        description: 'Creates a facility for the target organization.',
        responses: [
          HttpResponse::HTTP_CREATED => new Response(description: 'Facility created'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Invalid input'),
          HttpResponse::HTTP_CONFLICT => new Response(description: 'Facility code already exists in this organization'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Parent facility not found'),
        ],
      ),
    ),
    new GetCollection(
      name: FacilityOperations::LIST_FACILITIES,
      uriTemplate: '/{organizationId}/facilities',
      input: false,
      output: FacilityOutput::class,
      provider: ListFacilitiesProvider::class,
      paginationEnabled: true,
      paginationClientItemsPerPage: true,
      // 200 rather than the 100 every other collection caps at: the frontend's
      // facility store loads every parent option in one page for the create
      // form's picker, and a lower ceiling would silently drop options past
      // the cut rather than fail.
      paginationMaximumItemsPerPage: 200,
      paginationItemsPerPage: 30,
      normalizationContext: ['groups' => [FacilitySerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Facility'],
        summary: 'List facilities',
        description: 'Lists facilities for the target organization.',
        parameters: [
          new Parameter(
            name: 'includeArchived',
            in: 'query',
            required: false,
            description: 'When true, archived facilities are included. Default: false.',
            schema: ['type' => 'boolean', 'default' => false],
          ),
          new Parameter(
            name: 'type',
            in: 'query',
            required: false,
            description: 'Filter by facility type.',
            schema: ['type' => 'string', 'enum' => ['site', 'building', 'floor', 'zone', 'area']],
          ),
          new Parameter(
            name: 'status',
            in: 'query',
            required: false,
            description: 'Filter by facility status.',
            schema: ['type' => 'string', 'enum' => ['active', 'archived']],
          ),
          new Parameter(
            name: 'parentFacilityId',
            in: 'query',
            required: false,
            description: 'Filter by direct parent facility identifier.',
            schema: ['type' => 'string', 'format' => 'uuid'],
          ),
          new Parameter(
            name: 'rootsOnly',
            in: 'query',
            required: false,
            description: 'When true, only facilities without a parent are returned. Cannot be combined with parentFacilityId.',
            schema: ['type' => 'boolean', 'default' => false],
          ),
          new Parameter(
            name: 'code',
            in: 'query',
            required: false,
            description: 'Filter by exact facility code.',
            schema: ['type' => 'string'],
          ),
          new Parameter(
            name: 'hasCoordinates',
            in: 'query',
            required: false,
            description: 'When true, only facilities with both latitude and longitude set are returned. When false, only facilities missing coordinates are returned. Omit for no coordinate filtering.',
            schema: ['type' => 'boolean'],
          ),
        ],
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Facilities retrieved'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Invalid organization identifier'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
        ],
      ),
    ),
    new Get(
      name: FacilityOperations::EXPORT_FACILITIES,
      description: 'Streams a bounded CSV export of facilities using the same filter subset as the list endpoint.',
      uriTemplate: '/{organizationId}/facilities/export',
      controller: ExportFacilitiesController::class,
      read: false,
      write: false,
      deserialize: false,
      serialize: false,
      output: false,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Facility'],
        summary: 'Export facilities (CSV)',
        description: 'Streams a CSV export of facilities (Content-Disposition: attachment) for the given '
          . 'organization, using the same filter subset as the list endpoint. Requires '
          . '`organization.facilities.read` on the organization, resolved the same way the list endpoint '
          . 'resolves it — a resource-level ROLE_USER check alone does not grant access. Bounded to '
          . ExportFacilitiesHandler::MAX_EXPORT_ROWS . ' matching rows — the request is rejected with 422 if '
          . 'the filters match more; narrow with a more specific filter and retry. The first seven CSV columns '
          . '(type, name, code, address, latitude, longitude, parentCode) are the round-trip contract read back '
          . 'by the bulk CSV import.',
        security: [['bearerAuth' => []]],
        parameters: [
          new Parameter(
            name: 'includeArchived',
            in: 'query',
            required: false,
            description: 'When true, archived facilities are included. Default: false.',
            schema: ['type' => 'boolean', 'default' => false],
          ),
          new Parameter(
            name: 'type',
            in: 'query',
            required: false,
            description: 'Filter by facility type.',
            schema: ['type' => 'string', 'enum' => ['site', 'building', 'floor', 'zone', 'area']],
          ),
          new Parameter(
            name: 'status',
            in: 'query',
            required: false,
            description: 'Filter by facility status.',
            schema: ['type' => 'string', 'enum' => ['active', 'archived']],
          ),
          new Parameter(
            name: 'parentFacilityId',
            in: 'query',
            required: false,
            description: 'Filter by direct parent facility identifier.',
            schema: ['type' => 'string', 'format' => 'uuid'],
          ),
          new Parameter(
            name: 'rootsOnly',
            in: 'query',
            required: false,
            description: 'When true, only facilities without a parent are returned. Cannot be combined with parentFacilityId.',
            schema: ['type' => 'boolean', 'default' => false],
          ),
          new Parameter(
            name: 'code',
            in: 'query',
            required: false,
            description: 'Filter by exact facility code.',
            schema: ['type' => 'string'],
          ),
          new Parameter(
            name: 'search',
            in: 'query',
            required: false,
            description: 'Text search across facility name/code.',
            schema: ['type' => 'string'],
          ),
          new Parameter(
            name: 'hasCoordinates',
            in: 'query',
            required: false,
            description: 'When true, only facilities with both latitude and longitude set are returned. When false, only facilities missing coordinates are returned. Omit for no coordinate filtering.',
            schema: ['type' => 'boolean'],
          ),
        ],
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'CSV export streamed successfully'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Invalid organization identifier or an invalid enum filter value'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Authenticated but missing organization.facilities.read'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'The organization is outside the caller\'s scope'),
          HttpResponse::HTTP_UNPROCESSABLE_ENTITY => new Response(description: 'Export exceeds the row cap; narrow the filters and retry'),
        ],
      ),
    ),
    // Declared BEFORE GET_FACILITY on purpose: `/facilities/geocode` and
    // `/facilities/{facilityId}` share a prefix, and the router matches in
    // declaration order — after the item route, "geocode" would be read as a
    // facilityId (same reason the export operation sits above too).
    new Get(
      name: FacilityOperations::GEOCODE_ADDRESS,
      uriTemplate: '/{organizationId}/facilities/geocode',
      input: false,
      output: GeocodeAddressOutput::class,
      provider: GeocodeAddressProvider::class,
      normalizationContext: ['groups' => [FacilitySerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Facility'],
        summary: 'Geocode an address',
        description: 'Resolves a free-form postal address to WGS 84 coordinates as an aid to facility data '
          . 'entry. The lookup is proxied server-side through the configured geocoding provider (Nominatim '
          . 'by default) — the browser never calls the provider directly. Requires '
          . '`organization.facilities.write` on the organization (resolved via resolveAccess): geocoding '
          . 'exists to FILL a facility\'s coordinates, so it is gated as a write-path aid, not a read. '
          . 'Rate limited to 30 requests per minute per user; the shared outbound channel is additionally '
          . 'throttled server-side to 1 request per second (Nominatim usage policy).',
        parameters: [
          new Parameter(
            name: 'address',
            in: 'query',
            required: true,
            description: 'Free-form postal address to resolve (1 to ' . GeocodeAddressHandler::MAX_ADDRESS_LENGTH . ' characters).',
            schema: ['type' => 'string', 'maxLength' => GeocodeAddressHandler::MAX_ADDRESS_LENGTH],
          ),
        ],
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Best-match coordinates for the address'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Missing, empty, or too-long address'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Authenticated but missing organization.facilities.write'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'No coordinates found for the address (or the organization is outside the caller\'s scope)'),
          HttpResponse::HTTP_TOO_MANY_REQUESTS => new Response(description: 'More than 30 geocoding requests in a minute'),
        ],
      ),
    ),
    new Get(
      name: FacilityOperations::GET_FACILITY,
      uriTemplate: '/{organizationId}/facilities/{facilityId}',
      input: false,
      output: FacilityOutput::class,
      provider: GetFacilityProvider::class,
      normalizationContext: ['groups' => [FacilitySerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Facility'],
        summary: 'Get facility',
        description: 'Returns one facility by identifier for the target organization.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Facility retrieved'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Invalid identifier'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Facility not found'),
        ],
      ),
    ),
    new GetCollection(
      name: FacilityOperations::LIST_FACILITY_CHILDREN,
      uriTemplate: '/{organizationId}/facilities/{facilityId}/children',
      input: false,
      output: FacilityOutput::class,
      provider: ListFacilityChildrenProvider::class,
      paginationEnabled: true,
      paginationClientItemsPerPage: true,
      // 200 rather than the 100 every other collection caps at: the frontend's
      // facility-plans store asks for a facility's children in one page to
      // build its zone-candidate list, and a lower ceiling would silently drop
      // candidates past the cut rather than fail.
      paginationMaximumItemsPerPage: 200,
      paginationItemsPerPage: 30,
      normalizationContext: ['groups' => [FacilitySerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Facility'],
        summary: 'List facility children',
        description: 'Lists direct children for one facility. This is the lazy tree expansion endpoint.',
        parameters: [
          new Parameter(name: 'includeArchived', in: 'query', required: false, description: 'When true, archived children are included.', schema: ['type' => 'boolean', 'default' => false]),
          new Parameter(name: 'search', in: 'query', required: false, description: 'Text search across child facilities.', schema: ['type' => 'string']),
        ],
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Facility children retrieved'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Invalid identifier'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Facility not found'),
        ],
      ),
    ),
    new GetCollection(
      name: FacilityOperations::LIST_FACILITY_DESCENDANTS,
      uriTemplate: '/{organizationId}/facilities/{facilityId}/descendants',
      input: false,
      output: FacilityOutput::class,
      provider: ListFacilityDescendantsProvider::class,
      paginationEnabled: false,
      normalizationContext: ['groups' => [FacilitySerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Facility'],
        summary: 'List facility descendants',
        description: 'Lists all descendants for one facility.',
        parameters: [
          new Parameter(name: 'includeArchived', in: 'query', required: false, description: 'When true, archived descendants are included.', schema: ['type' => 'boolean', 'default' => false]),
          new Parameter(name: 'search', in: 'query', required: false, description: 'Text search across descendant facilities.', schema: ['type' => 'string']),
        ],
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Facility descendants retrieved'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Invalid identifier'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Facility not found'),
        ],
      ),
    ),
    new Patch(
      name: FacilityOperations::UPDATE_FACILITY,
      uriTemplate: '/{organizationId}/facilities/{facilityId}',
      read: false,
      input: UpdateFacilityInput::class,
      output: FacilityOutput::class,
      processor: UpdateFacilityProcessor::class,
      denormalizationContext: ['groups' => [FacilitySerializationGroup::WRITE]],
      normalizationContext: ['groups' => [FacilitySerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Facility'],
        summary: 'Patch facility',
        description: 'Partially updates facility information.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Facility updated'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Invalid input'),
          HttpResponse::HTTP_CONFLICT => new Response(description: 'Facility code already exists in this organization'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Facility not found'),
        ],
      ),
    ),
    new Post(
      name: FacilityOperations::ARCHIVE_FACILITY,
      uriTemplate: '/{organizationId}/facilities/{facilityId}/archive',
      status: HttpResponse::HTTP_OK,
      input: false,
      output: FacilityOutput::class,
      processor: ArchiveFacilityProcessor::class,
      normalizationContext: ['groups' => [FacilitySerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Facility'],
        summary: 'Archive facility',
        description: 'Archives one facility.',
        deprecated: true,
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Facility archived'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Invalid identifier'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Facility not found'),
        ],
      ),
    ),
    new Patch(
      name: FacilityOperations::RESTORE_FACILITY,
      uriTemplate: '/{organizationId}/facilities/{facilityId}/restore',
      input: false,
      output: FacilityOutput::class,
      processor: RestoreFacilityProcessor::class,
      normalizationContext: ['groups' => [FacilitySerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Facility'],
        summary: 'Restore facility',
        description: 'Restores an archived facility.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Facility restored'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Invalid identifier or parent state'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Facility not found'),
        ],
      ),
    ),
    new Post(
      name: FacilityOperations::MOVE_FACILITY,
      uriTemplate: '/{organizationId}/facilities/{facilityId}/move',
      status: HttpResponse::HTTP_OK,
      input: MoveFacilityInput::class,
      output: FacilityOutput::class,
      processor: MoveFacilityProcessor::class,
      denormalizationContext: ['groups' => [FacilitySerializationGroup::WRITE]],
      normalizationContext: ['groups' => [FacilitySerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Facility'],
        summary: 'Move facility',
        description: 'Moves a facility under a new parent.',
        deprecated: true,
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Facility moved'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Invalid hierarchy'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Facility not found'),
        ],
      ),
    ),
    new Put(
      name: FacilityOperations::SET_FACILITY_PLAN_GEOMETRY,
      uriTemplate: '/{organizationId}/facilities/{facilityId}/plan-geometry',
      read: false,
      input: SetFacilityPlanGeometryInput::class,
      output: FacilityOutput::class,
      processor: SetFacilityPlanGeometryProcessor::class,
      denormalizationContext: ['groups' => [FacilitySerializationGroup::WRITE]],
      normalizationContext: ['groups' => [FacilitySerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Facility'],
        summary: 'Set facility plan geometry',
        description: 'Binds this facility (a spatial zone) to a polygon drawn over an ancestor floor plan attachment. Submit "attachmentId" and "points" (>=3 normalized 0-1 coordinates) together to set or replace the geometry, or both null to clear it.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Plan geometry set or cleared'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Invalid geometry shape, bounds, or point count'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Facility or attachment not found'),
          HttpResponse::HTTP_CONFLICT => new Response(description: 'Attachment is not a floor plan, or does not belong to this facility or an ancestor'),
        ],
      ),
    ),
    new Get(
      name: FacilityOperations::GET_FACILITY_PLAN_OVERLAY,
      uriTemplate: '/{organizationId}/facilities/{facilityId}/plan-overlay',
      input: false,
      output: FacilityPlanOverlayOutput::class,
      provider: FacilityPlanOverlayProvider::class,
      normalizationContext: ['groups' => [FacilitySerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Facility'],
        summary: 'Get facility plan overlay',
        description: 'Returns one floor plan (explicit "attachmentId", or this facility\'s own primary plan when omitted) and every published self-or-descendant zone bound to it.',
        parameters: [
          new Parameter(
            name: 'attachmentId',
            in: 'query',
            required: false,
            description: 'Floor plan attachment identifier. Defaults to this facility\'s primary plan when omitted.',
            schema: ['type' => 'string', 'format' => 'uuid'],
          ),
        ],
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Plan overlay retrieved'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Invalid identifier'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Facility or attachment not found'),
          HttpResponse::HTTP_CONFLICT => new Response(description: 'Attachment is not a floor plan, or does not belong to this facility or an ancestor'),
        ],
      ),
    ),
    new Post(
      name: FacilityOperations::DUPLICATE_FACILITY_SUBTREE,
      uriTemplate: '/{organizationId}/facilities/{facilityId}/duplicate',
      input: DuplicateFacilitySubtreeInput::class,
      output: FacilityOutput::class,
      processor: DuplicateFacilitySubtreeProcessor::class,
      status: HttpResponse::HTTP_CREATED,
      denormalizationContext: ['groups' => [FacilitySerializationGroup::WRITE]],
      normalizationContext: ['groups' => [FacilitySerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Facility'],
        summary: 'Duplicate facility subtree',
        description: 'Duplicates a facility and its full subtree into a new, independent branch.',
        responses: [
          HttpResponse::HTTP_CREATED => new Response(description: 'Facility subtree duplicated'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Invalid input or target parent'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Facility not found'),
          HttpResponse::HTTP_CONFLICT => new Response(description: 'Source facility is archived, or the organization plan quota would be exceeded'),
          HttpResponse::HTTP_UNPROCESSABLE_ENTITY => new Response(description: 'Subtree exceeds the duplication size limit'),
        ],
      ),
    ),
  ],
)]
/**
 * Resource FacilityResource.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FacilityResource
{
}
