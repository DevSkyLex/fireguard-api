<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, GetCollection};
use ApiPlatform\OpenApi\Model\{Operation, Response};
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationLegalTypeOptionOutput;
use Organization\Presentation\Api\Operation\OrganizationOperations;
use Organization\Presentation\Api\Provider\Organization\ListOrganizationLegalTypesProvider;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

#[ApiResource(
  shortName: 'OrganizationLegalType',
  routePrefix: '/organizations',
  description: 'Reference data for organization legal entity types.',
  operations: [
    new GetCollection(
      name: OrganizationOperations::LIST_ORGANIZATION_LEGAL_TYPES,
      uriTemplate: '/legal-types',
      input: false,
      output: OrganizationLegalTypeOptionOutput::class,
      provider: ListOrganizationLegalTypesProvider::class,
      normalizationContext: ['groups' => [OrganizationSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      cacheHeaders: ['max_age' => 3600, 'shared_max_age' => 0],
      openapi: new Operation(
        tags: ['Organization'],
        summary: 'List organization legal types',
        description: 'Returns organization legal entity type values for the Legal profile settings tab select.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Organization legal types retrieved'),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(description: 'Authentication required'),
        ],
      ),
    ),
  ],
)]
final class OrganizationLegalTypeResource
{
}
