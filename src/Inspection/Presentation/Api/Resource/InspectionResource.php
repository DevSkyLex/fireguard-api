<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get, GetCollection, Post};
use ApiPlatform\OpenApi\Model\{Operation, Parameter, Response};
use Inspection\Presentation\Api\Dto\Input\Inspection\CreateInspectionInput;
use Inspection\Presentation\Api\Dto\Output\Inspection\InspectionOutput;
use Inspection\Presentation\Api\Operation\InspectionOperations;
use Inspection\Presentation\Api\Processor\Inspection\{
  CloseInspectionProcessor,
  CreateInspectionProcessor,
  SubmitInspectionProcessor
};
use Inspection\Presentation\Api\Provider\Inspection\{GetInspectionProvider, ListInspectionsProvider};
use Inspection\Presentation\Api\Serialization\InspectionSerializationGroup;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

#[ApiResource(
  shortName: 'Inspection',
  routePrefix: '/organizations',
  description: 'Inspection records for fire safety equipment.',
  operations: [
    new Post(
      name: InspectionOperations::CREATE_INSPECTION,
      uriTemplate: '/{organizationId}/inspections',
      input: CreateInspectionInput::class,
      output: InspectionOutput::class,
      processor: CreateInspectionProcessor::class,
      denormalizationContext: ['groups' => [InspectionSerializationGroup::WRITE]],
      normalizationContext: ['groups' => [InspectionSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Inspection'],
        summary: 'Create an inspection',
        description: 'Creates a new inspection for an equipment item.',
        responses: [
          HttpResponse::HTTP_CREATED => new Response(description: 'Inspection created'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Invalid input'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
        ],
      ),
    ),
    new GetCollection(
      name: InspectionOperations::LIST_INSPECTIONS,
      uriTemplate: '/{organizationId}/inspections',
      input: false,
      output: InspectionOutput::class,
      provider: ListInspectionsProvider::class,
      paginationEnabled: true,
      paginationClientItemsPerPage: true,
      paginationItemsPerPage: 30,
      normalizationContext: ['groups' => [InspectionSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Inspection'],
        summary: 'List inspections',
        description: 'Lists all inspections for the organization.',
        parameters: [
          new Parameter(name: 'equipmentId', in: 'query', description: 'Filter by equipment', required: false, schema: ['type' => 'string']),
          new Parameter(name: 'facilityId', in: 'query', description: 'Filter by facility', required: false, schema: ['type' => 'string']),
          new Parameter(name: 'result', in: 'query', description: 'Filter by result (pass, fail, partial)', required: false, schema: ['type' => 'string']),
          new Parameter(name: 'status', in: 'query', description: 'Filter by status (draft, submitted, closed)', required: false, schema: ['type' => 'string']),
        ],
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Inspection list'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
        ],
      ),
    ),
    new Get(
      name: InspectionOperations::GET_INSPECTION,
      uriTemplate: '/{organizationId}/inspections/{inspectionId}',
      input: false,
      output: InspectionOutput::class,
      provider: GetInspectionProvider::class,
      normalizationContext: ['groups' => [InspectionSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Inspection'],
        summary: 'Get an inspection',
        description: 'Retrieves detailed information about a specific inspection.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Inspection details'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Inspection not found'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
        ],
      ),
    ),
    new Post(
      name: InspectionOperations::SUBMIT_INSPECTION,
      uriTemplate: '/{organizationId}/inspections/{inspectionId}/submit',
      input: false,
      output: InspectionOutput::class,
      processor: SubmitInspectionProcessor::class,
      normalizationContext: ['groups' => [InspectionSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Inspection'],
        summary: 'Submit an inspection',
        description: 'Submits a draft inspection for review.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Inspection submitted'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Inspection not found'),
          HttpResponse::HTTP_CONFLICT => new Response(description: 'Inspection already closed'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
        ],
      ),
    ),
    new Post(
      name: InspectionOperations::CLOSE_INSPECTION,
      uriTemplate: '/{organizationId}/inspections/{inspectionId}/close',
      input: false,
      output: InspectionOutput::class,
      processor: CloseInspectionProcessor::class,
      normalizationContext: ['groups' => [InspectionSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Inspection'],
        summary: 'Close an inspection',
        description: 'Closes a submitted inspection.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Inspection closed'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Inspection not found'),
          HttpResponse::HTTP_CONFLICT => new Response(description: 'Inspection already closed'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
        ],
      ),
    ),
  ],
)]
final class InspectionResource
{
}
