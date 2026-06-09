<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, GetCollection};
use ApiPlatform\OpenApi\Model\{Operation, Response};
use Inspection\Presentation\Api\Dto\Output\Inspection\InspectionOptionOutput;
use Inspection\Presentation\Api\Operation\InspectionOperations;
use Inspection\Presentation\Api\Provider\Checklist\ListChecklistStatusesProvider;
use Inspection\Presentation\Api\Serialization\InspectionSerializationGroup;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

#[ApiResource(
  shortName: 'ChecklistCatalog',
  routePrefix: '/checklists',
  description: 'Reference data used by checklist forms and filters.',
  operations: [
    new GetCollection(
      name: InspectionOperations::LIST_CHECKLIST_STATUSES,
      uriTemplate: '/statuses',
      input: false,
      output: InspectionOptionOutput::class,
      provider: ListChecklistStatusesProvider::class,
      normalizationContext: ['groups' => [InspectionSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      cacheHeaders: ['max_age' => 3600, 'shared_max_age' => 0],
      openapi: new Operation(
        tags: ['Checklist'],
        summary: 'List checklist statuses',
        description: 'Returns supported checklist status values for filters and select inputs.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Checklist statuses retrieved successfully'),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(description: 'Authentication required'),
        ],
      ),
    ),
  ],
)]
final class ChecklistCatalogResource
{
}
