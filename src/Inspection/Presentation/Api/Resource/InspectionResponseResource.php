<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Delete, Get, GetCollection, Patch, Post, Put};
use ApiPlatform\OpenApi\Model\{Operation, Parameter};
use Inspection\Presentation\Api\Dto\Input\InspectionResponse\{CreateInspectionResponseInput, PatchInspectionResponseInput};
use Inspection\Presentation\Api\Dto\Output\InspectionResponse\InspectionResponseOutput;
use Inspection\Presentation\Api\Processor\InspectionResponse\InspectionResponseProcessor;
use Inspection\Presentation\Api\Provider\InspectionResponse\InspectionResponseProvider;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resource InspectionResponseResource.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'InspectionResponse',
  operations: [
    new Post(uriTemplate: '/inspection-responses', input: CreateInspectionResponseInput::class, output: InspectionResponseOutput::class, processor: InspectionResponseProcessor::class, security: "is_granted('ROLE_USER')"),
    new Put(name: 'inspection_response_put', uriTemplate: '/inspection-responses/{id}', read: false, input: CreateInspectionResponseInput::class, output: InspectionResponseOutput::class, processor: InspectionResponseProcessor::class, status: Response::HTTP_CREATED, security: "is_granted('ROLE_USER')"),
    new GetCollection(
      uriTemplate: '/inspection-responses',
      output: InspectionResponseOutput::class,
      provider: InspectionResponseProvider::class,
      paginationEnabled: true,
      paginationClientItemsPerPage: true,
      paginationItemsPerPage: 50,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(parameters: [
        new Parameter(name: 'organization', in: 'query', description: 'Organization IRI. Required when mission and inspection are omitted.', required: false, schema: ['type' => 'string']),
        new Parameter(name: 'mission', in: 'query', description: 'Mission IRI. Also scopes the organization and defaults recordStatus to draft.', required: false, schema: ['type' => 'string']),
        new Parameter(name: 'inspection', in: 'query', description: 'Inspection IRI. Also scopes the organization.', required: false, schema: ['type' => 'string']),
        new Parameter(name: 'recordStatus', in: 'query', description: 'Lifecycle status of the representation.', required: false, schema: ['type' => 'string', 'enum' => ['draft', 'published']]),
      ]),
    ),
    new Get(uriTemplate: '/inspection-responses/{id}', output: InspectionResponseOutput::class, provider: InspectionResponseProvider::class, security: "is_granted('ROLE_USER')"),
    new Patch(uriTemplate: '/inspection-responses/{id}', read: false, input: PatchInspectionResponseInput::class, output: InspectionResponseOutput::class, processor: InspectionResponseProcessor::class, security: "is_granted('ROLE_USER')"),
    new Delete(uriTemplate: '/inspection-responses/{id}', read: false, input: false, output: false, processor: InspectionResponseProcessor::class, status: Response::HTTP_NO_CONTENT, security: "is_granted('ROLE_USER')"),
  ],
)]
final class InspectionResponseResource
{
}
