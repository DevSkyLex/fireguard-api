<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get, GetCollection, Patch, Post};
use ApiPlatform\OpenApi\Model\{Operation, Parameter, Response};
use Facility\Presentation\Api\Dto\Input\Facility\{
  CreateFacilityInput,
  MoveFacilityInput,
  UpdateFacilityInput
};
use Facility\Presentation\Api\Dto\Output\Facility\FacilityOutput;
use Facility\Presentation\Api\Operation\FacilityOperations;
use Facility\Presentation\Api\Processor\Facility\{
  ArchiveFacilityProcessor,
  CreateFacilityProcessor,
  MoveFacilityProcessor,
  RestoreFacilityProcessor,
  UpdateFacilityProcessor
};
use Facility\Presentation\Api\Provider\Facility\{GetFacilityProvider, ListFacilitiesProvider, ListFacilityChildrenProvider, ListFacilityDescendantsProvider};
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
