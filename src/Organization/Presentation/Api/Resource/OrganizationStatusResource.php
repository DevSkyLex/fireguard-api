<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, GetCollection};
use ApiPlatform\OpenApi\Model\{Operation, Response};
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationStatusOptionOutput;
use Organization\Presentation\Api\Operation\OrganizationOperations;
use Organization\Presentation\Api\Provider\Organization\ListOrganizationStatusesProvider;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

#[ApiResource(
  shortName: 'OrganizationStatus',
  routePrefix: '/organizations',
  description: 'Reference data for organization statuses.',
  operations: [
    new GetCollection(
      name: OrganizationOperations::LIST_ORGANIZATION_STATUSES,
      uriTemplate: '/statuses',
      input: false,
      output: OrganizationStatusOptionOutput::class,
      provider: ListOrganizationStatusesProvider::class,
      normalizationContext: ['groups' => [OrganizationSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      cacheHeaders: ['max_age' => 3600, 'shared_max_age' => 0],
      openapi: new Operation(
        tags: ['Organization'],
        summary: 'List organization statuses',
        description: 'Returns organization status values for filters and UI selects.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Organization statuses retrieved'),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(description: 'Authentication required'),
        ],
      ),
    ),
  ],
)]
final class OrganizationStatusResource
{
}
