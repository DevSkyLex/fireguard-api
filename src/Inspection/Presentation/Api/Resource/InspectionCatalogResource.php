<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, GetCollection};
use ApiPlatform\OpenApi\Model\{Operation, Response};
use Inspection\Presentation\Api\Dto\Output\Inspection\InspectionOptionOutput;
use Inspection\Presentation\Api\Operation\InspectionOperations;
use Inspection\Presentation\Api\Provider\Inspection\{ListInspectionResultsProvider, ListInspectionStatusesProvider, ListInspectorTypesProvider};
use Inspection\Presentation\Api\Serialization\InspectionSerializationGroup;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

#[ApiResource(
  shortName: 'InspectionCatalog',
  routePrefix: '/inspections',
  description: 'Reference data used by inspection forms and filters.',
  operations: [
    new GetCollection(
      name: InspectionOperations::LIST_INSPECTION_RESULTS,
      uriTemplate: '/results',
      input: false,
      output: InspectionOptionOutput::class,
      provider: ListInspectionResultsProvider::class,
      normalizationContext: ['groups' => [InspectionSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Inspection'],
        summary: 'List inspection results',
        description: 'Returns supported inspection result values for filters and select inputs.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Inspection results retrieved successfully'),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(description: 'Authentication required'),
        ],
      ),
    ),
    new GetCollection(
      name: InspectionOperations::LIST_INSPECTION_STATUSES,
      uriTemplate: '/statuses',
      input: false,
      output: InspectionOptionOutput::class,
      provider: ListInspectionStatusesProvider::class,
      normalizationContext: ['groups' => [InspectionSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Inspection'],
        summary: 'List inspection statuses',
        description: 'Returns supported inspection status values for filters and UI badges.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Inspection statuses retrieved successfully'),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(description: 'Authentication required'),
        ],
      ),
    ),
    new GetCollection(
      name: InspectionOperations::LIST_INSPECTOR_TYPES,
      uriTemplate: '/inspector-types',
      input: false,
      output: InspectionOptionOutput::class,
      provider: ListInspectorTypesProvider::class,
      normalizationContext: ['groups' => [InspectionSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Inspection'],
        summary: 'List inspector types',
        description: 'Returns supported inspector types for inspection creation flows.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Inspector types retrieved successfully'),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(description: 'Authentication required'),
        ],
      ),
    ),
  ],
)]
final class InspectionCatalogResource
{
}
