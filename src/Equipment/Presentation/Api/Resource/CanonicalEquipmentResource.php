<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Delete, Get, GetCollection, Patch, Post, Put};
use ApiPlatform\OpenApi\Model\{Operation, Parameter};
use Equipment\Presentation\Api\Dto\Input\Equipment\{CreateEquipmentInput, PatchCanonicalEquipmentInput};
use Equipment\Presentation\Api\Dto\Output\Equipment\EquipmentOutput;
use Equipment\Presentation\Api\Processor\Equipment\{CanonicalEquipmentMutationProcessor, CreateEquipmentProcessor};
use Equipment\Presentation\Api\Provider\Equipment\CanonicalEquipmentProvider;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resource CanonicalEquipmentResource.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'Equipment',
  operations: [
    new Post(uriTemplate: '/equipment', input: CreateEquipmentInput::class, output: EquipmentOutput::class, processor: CreateEquipmentProcessor::class, security: "is_granted('ROLE_USER')"),
    new Put(name: 'canonical_equipment_put', uriTemplate: '/equipment/{id}', read: false, input: CreateEquipmentInput::class, output: EquipmentOutput::class, processor: CreateEquipmentProcessor::class, status: Response::HTTP_CREATED, security: "is_granted('ROLE_USER')"),
    new GetCollection(
      uriTemplate: '/equipment',
      output: EquipmentOutput::class,
      provider: CanonicalEquipmentProvider::class,
      paginationEnabled: true,
      paginationClientItemsPerPage: true,
      paginationMaximumItemsPerPage: 100,
      paginationItemsPerPage: 50,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(parameters: [
        new Parameter(name: 'organization', in: 'query', description: 'Organization IRI. Required when intervention is omitted.', required: false, schema: ['type' => 'string']),
        new Parameter(name: 'intervention', in: 'query', description: 'Intervention IRI. Also scopes the organization and defaults recordStatus to draft.', required: false, schema: ['type' => 'string']),
        new Parameter(name: 'facility', in: 'query', description: 'Facility IRI.', required: false, schema: ['type' => 'string']),
        new Parameter(name: 'recordStatus', in: 'query', description: 'Lifecycle status of the representation.', required: false, schema: ['type' => 'string', 'enum' => ['draft', 'published']]),
      ]),
    ),
    new Get(uriTemplate: '/equipment/{id}', output: EquipmentOutput::class, provider: CanonicalEquipmentProvider::class, security: "is_granted('ROLE_USER')"),
    new Patch(name: 'canonical_equipment_patch', uriTemplate: '/equipment/{id}', read: false, input: PatchCanonicalEquipmentInput::class, output: EquipmentOutput::class, processor: CanonicalEquipmentMutationProcessor::class, security: "is_granted('ROLE_USER')"),
    new Delete(name: 'canonical_equipment_delete', uriTemplate: '/equipment/{id}', read: false, input: false, output: false, processor: CanonicalEquipmentMutationProcessor::class, status: Response::HTTP_NO_CONTENT, security: "is_granted('ROLE_USER')"),
  ],
)]
final class CanonicalEquipmentResource
{
}
