<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, GetCollection};
use ApiPlatform\OpenApi\Model\{Operation, Response};
use Inspection\Presentation\Api\Dto\Output\Inspection\InspectionOptionOutput;
use Inspection\Presentation\Api\Operation\InspectionOperations;
use Inspection\Presentation\Api\Provider\NonConformity\ListNonConformityStatusesProvider;
use Inspection\Presentation\Api\Serialization\InspectionSerializationGroup;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

#[ApiResource(
  shortName: 'NonConformityCatalog',
  routePrefix: '/non-conformities',
  description: 'Reference data used by non-conformity forms and filters.',
  operations: [
    new GetCollection(
      name: InspectionOperations::LIST_NON_CONFORMITY_STATUSES,
      uriTemplate: '/statuses',
      input: false,
      output: InspectionOptionOutput::class,
      provider: ListNonConformityStatusesProvider::class,
      normalizationContext: ['groups' => [InspectionSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      cacheHeaders: ['max_age' => 3600, 'shared_max_age' => 0],
      openapi: new Operation(
        tags: ['NonConformity'],
        summary: 'List non-conformity statuses',
        description: 'Returns supported non-conformity status values for filters and select inputs.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Non-conformity statuses retrieved successfully'),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(description: 'Authentication required'),
        ],
      ),
    ),
  ],
)]
final class NonConformityCatalogResource
{
}
