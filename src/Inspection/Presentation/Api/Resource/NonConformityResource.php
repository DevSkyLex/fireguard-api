<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, GetCollection, Patch, Post};
use ApiPlatform\OpenApi\Model\{Operation, Parameter, Response};
use Inspection\Presentation\Api\Dto\Input\NonConformity\{AddNonConformityInput, UpdateNonConformityStatusInput};
use Inspection\Presentation\Api\Dto\Output\NonConformity\NonConformityOutput;
use Inspection\Presentation\Api\Operation\InspectionOperations;
use Inspection\Presentation\Api\Processor\NonConformity\{AddNonConformityProcessor, UpdateNonConformityStatusProcessor};
use Inspection\Presentation\Api\Provider\NonConformity\ListNonConformitiesProvider;
use Inspection\Presentation\Api\Serialization\InspectionSerializationGroup;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

#[ApiResource(
  shortName: 'NonConformity',
  routePrefix: '/organizations',
  description: 'Non-conformities detected during inspections.',
  operations: [
    new Post(
      name: InspectionOperations::ADD_NON_CONFORMITY,
      uriTemplate: '/{organizationId}/inspections/{inspectionId}/non-conformities',
      input: AddNonConformityInput::class,
      output: NonConformityOutput::class,
      processor: AddNonConformityProcessor::class,
      denormalizationContext: ['groups' => [InspectionSerializationGroup::WRITE]],
      normalizationContext: ['groups' => [InspectionSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Inspection'],
        summary: 'Add a non-conformity',
        description: 'Adds a non-conformity to an inspection.',
        responses: [
          HttpResponse::HTTP_CREATED => new Response(description: 'Non-conformity created'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Invalid input'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Inspection not found'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
        ],
      ),
    ),
    new GetCollection(
      name: InspectionOperations::LIST_NON_CONFORMITIES,
      uriTemplate: '/{organizationId}/inspections/{inspectionId}/non-conformities',
      input: false,
      output: NonConformityOutput::class,
      provider: ListNonConformitiesProvider::class,
      paginationEnabled: true,
      paginationClientItemsPerPage: true,
      paginationItemsPerPage: 30,
      normalizationContext: ['groups' => [InspectionSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Inspection'],
        summary: 'List non-conformities',
        description: 'Lists non-conformities for an inspection.',
        parameters: [
          new Parameter(name: 'severity', in: 'query', description: 'Filter by severity (low, medium, high, critical)', required: false, schema: ['type' => 'string']),
          new Parameter(name: 'status', in: 'query', description: 'Filter by status (open, in_progress, done, waived)', required: false, schema: ['type' => 'string']),
        ],
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Non-conformity list'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Inspection not found'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
        ],
      ),
    ),
    new Patch(
      name: InspectionOperations::UPDATE_NON_CONFORMITY_STATUS,
      uriTemplate: '/{organizationId}/inspections/{inspectionId}/non-conformities/{nonConformityId}/status',
      read: false,
      input: UpdateNonConformityStatusInput::class,
      output: NonConformityOutput::class,
      processor: UpdateNonConformityStatusProcessor::class,
      denormalizationContext: ['groups' => [InspectionSerializationGroup::WRITE]],
      normalizationContext: ['groups' => [InspectionSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Inspection'],
        summary: 'Update non-conformity status',
        description: 'Updates the status of a non-conformity.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Status updated'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Invalid input'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Non-conformity not found'),
          HttpResponse::HTTP_CONFLICT => new Response(description: 'Non-conformity already resolved'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
        ],
      ),
    ),
  ],
)]
final class NonConformityResource
{
}
