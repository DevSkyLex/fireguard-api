<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get, GetCollection, Patch, Post};
use ApiPlatform\OpenApi\Model\{Operation, Response};
use Equipment\Presentation\Api\Dto\Input\EquipmentTypeCatalog\{CreateEquipmentTypeInput, PatchEquipmentTypeInput};
use Equipment\Presentation\Api\Dto\Output\EquipmentTypeCatalog\EquipmentTypeOutput;
use Equipment\Presentation\Api\Operation\EquipmentTypeCatalogOperations;
use Equipment\Presentation\Api\Processor\EquipmentTypeCatalog\EquipmentTypeCatalogProcessor;
use Equipment\Presentation\Api\Provider\EquipmentTypeCatalog\EquipmentTypeCatalogProvider;

/**
 * Resource EquipmentTypeCatalogResource.
 *
 * Organization catalogs preserve historical codes while allowing managed additions.
 *
 * @category Resource
 */
#[ApiResource(
  shortName: 'EquipmentType',
  routePrefix: '/organizations',
  operations: [
    new GetCollection(
      name: EquipmentTypeCatalogOperations::LIST,
      uriTemplate: '/{organizationId}/equipment-types',
      input: false,
      output: EquipmentTypeOutput::class,
      provider: EquipmentTypeCatalogProvider::class,
      paginationEnabled: false,
      security: "is_granted('ROLE_USER')",
      normalizationContext: ['groups' => ['equipment_type:read']],
      openapi: new Operation(tags: ['Equipment'], summary: 'List organization equipment types, including archived types', responses: [
        200 => new Response(description: 'Catalog including archived descriptors'),
        403 => new Response(description: 'Missing equipment-read permission'),
        404 => new Response(description: 'Organization outside caller scope'),
      ]),
    ),
    new Get(
      name: EquipmentTypeCatalogOperations::GET,
      uriTemplate: '/{organizationId}/equipment-types/{typeCode}',
      input: false,
      output: EquipmentTypeOutput::class,
      provider: EquipmentTypeCatalogProvider::class,
      security: "is_granted('ROLE_USER')",
      normalizationContext: ['groups' => ['equipment_type:read']],
      openapi: new Operation(tags: ['Equipment'], summary: 'Read an organization equipment type', responses: [
        200 => new Response(description: 'Equipment type descriptor'),
        403 => new Response(description: 'Missing equipment-read permission'),
        404 => new Response(description: 'Organization outside caller scope or unknown equipment type'),
      ]),
    ),
    new Post(
      name: EquipmentTypeCatalogOperations::CREATE,
      uriTemplate: '/{organizationId}/equipment-types',
      status: 201,
      read: false,
      input: CreateEquipmentTypeInput::class,
      output: EquipmentTypeOutput::class,
      processor: EquipmentTypeCatalogProcessor::class,
      security: "is_granted('ROLE_USER')",
      denormalizationContext: ['groups' => ['equipment_type:write'], 'allow_extra_attributes' => false],
      normalizationContext: ['groups' => ['equipment_type:read']],
      openapi: new Operation(tags: ['Equipment'], summary: 'Create an organization equipment type', responses: [
        201 => new Response(description: 'Active equipment type created'),
        403 => new Response(description: 'Missing equipment-write permission'),
        404 => new Response(description: 'Organization outside caller scope'),
        409 => new Response(description: 'Type code already exists'),
        422 => new Response(description: 'Invalid code, label or family'),
      ]),
    ),
    new Patch(
      name: EquipmentTypeCatalogOperations::PATCH,
      uriTemplate: '/{organizationId}/equipment-types/{typeCode}',
      status: 200,
      read: false,
      input: PatchEquipmentTypeInput::class,
      output: EquipmentTypeOutput::class,
      processor: EquipmentTypeCatalogProcessor::class,
      security: "is_granted('ROLE_USER')",
      denormalizationContext: ['groups' => ['equipment_type:write'], 'allow_extra_attributes' => false],
      normalizationContext: ['groups' => ['equipment_type:read']],
      openapi: new Operation(tags: ['Equipment'], summary: 'Update or archive a type using its observed revision', responses: [
        200 => new Response(description: 'Equipment type updated'),
        403 => new Response(description: 'Missing equipment-write permission'),
        404 => new Response(description: 'Organization outside caller scope or unknown equipment type'),
        412 => new Response(description: 'Observed descriptor revision is stale'),
        422 => new Response(description: 'Invalid label, family or revision'),
      ]),
    ),
  ],
)]
final class EquipmentTypeCatalogResource
{
}
