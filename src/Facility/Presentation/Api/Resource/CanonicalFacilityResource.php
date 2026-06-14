<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Delete, Get, GetCollection, Patch, Post, Put};
use ApiPlatform\OpenApi\Model\{Operation, Parameter};
use Facility\Presentation\Api\Dto\Input\Facility\{CreateFacilityInput, PatchCanonicalFacilityInput};
use Facility\Presentation\Api\Dto\Output\Facility\FacilityOutput;
use Facility\Presentation\Api\Processor\Facility\{CanonicalFacilityMutationProcessor, CreateFacilityProcessor};
use Facility\Presentation\Api\Provider\Facility\CanonicalFacilityProvider;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resource CanonicalFacilityResource.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'Facility',
  operations: [
    new Post(uriTemplate: '/facilities', input: CreateFacilityInput::class, output: FacilityOutput::class, processor: CreateFacilityProcessor::class, security: "is_granted('ROLE_USER')"),
    new Put(name: 'canonical_facility_put', uriTemplate: '/facilities/{id}', read: false, input: CreateFacilityInput::class, output: FacilityOutput::class, processor: CreateFacilityProcessor::class, status: Response::HTTP_CREATED, security: "is_granted('ROLE_USER')"),
    new GetCollection(
      uriTemplate: '/facilities',
      output: FacilityOutput::class,
      provider: CanonicalFacilityProvider::class,
      paginationEnabled: true,
      paginationClientItemsPerPage: true,
      paginationItemsPerPage: 50,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(parameters: [
        new Parameter(name: 'organization', in: 'query', description: 'Organization IRI. Required when intervention is omitted.', required: false, schema: ['type' => 'string']),
        new Parameter(name: 'intervention', in: 'query', description: 'Intervention IRI. Also scopes the organization and defaults recordStatus to draft.', required: false, schema: ['type' => 'string']),
        new Parameter(name: 'recordStatus', in: 'query', description: 'Lifecycle status of the representation.', required: false, schema: ['type' => 'string', 'enum' => ['draft', 'published']]),
      ]),
    ),
    new Get(uriTemplate: '/facilities/{id}', output: FacilityOutput::class, provider: CanonicalFacilityProvider::class, security: "is_granted('ROLE_USER')"),
    new Patch(name: 'canonical_facility_patch', uriTemplate: '/facilities/{id}', read: false, input: PatchCanonicalFacilityInput::class, output: FacilityOutput::class, processor: CanonicalFacilityMutationProcessor::class, security: "is_granted('ROLE_USER')"),
    new Delete(name: 'canonical_facility_delete', uriTemplate: '/facilities/{id}', read: false, input: false, output: false, processor: CanonicalFacilityMutationProcessor::class, status: Response::HTTP_NO_CONTENT, security: "is_granted('ROLE_USER')"),
  ],
)]
final class CanonicalFacilityResource
{
}
