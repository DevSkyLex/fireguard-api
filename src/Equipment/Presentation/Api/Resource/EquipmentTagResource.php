<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Delete, GetCollection, Post};
use ApiPlatform\OpenApi\Model\{Operation, Parameter, Response};
use Equipment\Presentation\Api\Dto\Input\Equipment\AddTagInput;
use Equipment\Presentation\Api\Dto\Output\Equipment\TagOutput;
use Equipment\Presentation\Api\Operation\EquipmentOperations;
use Equipment\Presentation\Api\Processor\Equipment\{AddTagToEquipmentProcessor, RemoveTagFromEquipmentProcessor};
use Equipment\Presentation\Api\Provider\Tag\ListTagsProvider;
use Equipment\Presentation\Api\Serialization\EquipmentSerializationGroup;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Resource EquipmentTagResource.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'EquipmentTag',
  routePrefix: '/organizations',
  description: 'Tags associated with equipment items.',
  operations: [
    new GetCollection(
      name: EquipmentOperations::LIST_TAGS,
      uriTemplate: '/{organizationId}/equipment/tags',
      input: false,
      output: TagOutput::class,
      provider: ListTagsProvider::class,
      paginationEnabled: true,
      paginationClientItemsPerPage: true,
      paginationItemsPerPage: 30,
      normalizationContext: ['groups' => [EquipmentSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Equipment'],
        summary: 'List tag catalog',
        description: 'Lists all tags available in the organization catalog. Supports optional search for typeahead.',
        parameters: [
          new Parameter(
            name: 'search',
            in: 'query',
            required: false,
            description: 'Filter tags by name (case-insensitive substring match).',
            schema: ['type' => 'string'],
          ),
        ],
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Tag catalog retrieved'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Invalid organization identifier'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
        ],
      ),
    ),
    new Post(
      name: EquipmentOperations::ADD_TAG_TO_EQUIPMENT,
      uriTemplate: '/{organizationId}/equipment/{equipmentId}/tags',
      input: AddTagInput::class,
      output: TagOutput::class,
      processor: AddTagToEquipmentProcessor::class,
      denormalizationContext: ['groups' => [EquipmentSerializationGroup::WRITE]],
      normalizationContext: ['groups' => [EquipmentSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Equipment'],
        summary: 'Add tag to equipment',
        description: 'Adds a tag (created if not existing) to the equipment.',
        responses: [
          HttpResponse::HTTP_CREATED => new Response(description: 'Tag added'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Invalid input'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Equipment not found'),
        ],
      ),
    ),
    new Delete(
      name: EquipmentOperations::REMOVE_TAG_FROM_EQUIPMENT,
      uriTemplate: '/{organizationId}/equipment/{equipmentId}/tags/{tagId}',
      read: false,
      input: false,
      output: false,
      processor: RemoveTagFromEquipmentProcessor::class,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Equipment'],
        summary: 'Remove tag from equipment',
        description: 'Removes a tag from the equipment.',
        responses: [
          HttpResponse::HTTP_NO_CONTENT => new Response(description: 'Tag removed'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Invalid identifier'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Equipment or tag not found'),
        ],
      ),
    ),
  ],
)]
final class EquipmentTagResource
{
}
