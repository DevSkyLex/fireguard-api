<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Delete, GetCollection, Patch, Post};
use ApiPlatform\OpenApi\Model\{Operation, Response};
use Facility\Presentation\Api\Dto\Input\MetadataField\{CreateFacilityMetadataFieldInput, UpdateFacilityMetadataFieldInput};
use Facility\Presentation\Api\Dto\Output\MetadataField\FacilityMetadataFieldOutput;
use Facility\Presentation\Api\Operation\FacilityOperations;
use Facility\Presentation\Api\Processor\MetadataField\{
  CreateFacilityMetadataFieldProcessor,
  DeleteFacilityMetadataFieldProcessor,
  UpdateFacilityMetadataFieldProcessor
};
use Facility\Presentation\Api\Provider\MetadataField\ListFacilityMetadataFieldsProvider;
use Facility\Presentation\Api\Serialization\FacilitySerializationGroup;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Resource FacilityMetadataFieldResource.
 *
 * An organization-defined, typed schema for facility `metadata` (EU-generic:
 * no national fire-safety regime is presumed). Deleting a field definition
 * does not touch any facility's already-stored `metadata` values — they
 * become "unschema'd" free-form values again, which is the deliberate
 * back-compat contract this feature is built on (see
 * `Facility\Application\Service\FacilityMetadataSchemaGuard`).
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'FacilityMetadataField',
  routePrefix: '/organizations',
  description: 'Organization-defined typed schema for facility metadata.',
  operations: [
    new Post(
      name: FacilityOperations::CREATE_FACILITY_METADATA_FIELD,
      uriTemplate: '/{organizationId}/facility-metadata-fields',
      input: CreateFacilityMetadataFieldInput::class,
      output: FacilityMetadataFieldOutput::class,
      processor: CreateFacilityMetadataFieldProcessor::class,
      denormalizationContext: ['groups' => [FacilitySerializationGroup::WRITE]],
      normalizationContext: ['groups' => [FacilitySerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Facility'],
        summary: 'Create a facility metadata field definition',
        description: 'Creates one typed metadata field definition for the organization.',
        responses: [
          HttpResponse::HTTP_CREATED => new Response(description: 'Metadata field created'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Invalid input'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Organization not found'),
          HttpResponse::HTTP_CONFLICT => new Response(description: 'Metadata field key already exists for this organization'),
          HttpResponse::HTTP_UNPROCESSABLE_ENTITY => new Response(description: 'Organization has reached the metadata field definition cap (50)'),
        ],
      ),
    ),
    new GetCollection(
      name: FacilityOperations::LIST_FACILITY_METADATA_FIELDS,
      uriTemplate: '/{organizationId}/facility-metadata-fields',
      input: false,
      output: FacilityMetadataFieldOutput::class,
      provider: ListFacilityMetadataFieldsProvider::class,
      paginationEnabled: false,
      normalizationContext: ['groups' => [FacilitySerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Facility'],
        summary: 'List facility metadata field definitions',
        description: 'Lists the organization\'s typed metadata field definitions. Doubles as the frontend\'s form-schema source.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Metadata field definitions retrieved'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Invalid organization identifier'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Organization not found'),
        ],
      ),
    ),
    new Patch(
      name: FacilityOperations::UPDATE_FACILITY_METADATA_FIELD,
      uriTemplate: '/{organizationId}/facility-metadata-fields/{id}',
      read: false,
      input: UpdateFacilityMetadataFieldInput::class,
      output: FacilityMetadataFieldOutput::class,
      processor: UpdateFacilityMetadataFieldProcessor::class,
      denormalizationContext: ['groups' => [FacilitySerializationGroup::WRITE]],
      normalizationContext: ['groups' => [FacilitySerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Facility'],
        summary: 'Patch a facility metadata field definition',
        description: 'Partially updates one metadata field definition.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Metadata field updated'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Invalid input'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Metadata field not found'),
        ],
      ),
    ),
    new Delete(
      name: FacilityOperations::DELETE_FACILITY_METADATA_FIELD,
      uriTemplate: '/{organizationId}/facility-metadata-fields/{id}',
      read: false,
      input: false,
      output: false,
      processor: DeleteFacilityMetadataFieldProcessor::class,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Facility'],
        summary: 'Delete a facility metadata field definition',
        description: 'Deletes one metadata field definition. Existing facility metadata values for this key are left untouched.',
        responses: [
          HttpResponse::HTTP_NO_CONTENT => new Response(description: 'Metadata field deleted'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Metadata field not found'),
        ],
      ),
    ),
  ],
)]
final class FacilityMetadataFieldResource
{
}
