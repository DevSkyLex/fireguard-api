<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, GetCollection};
use ApiPlatform\OpenApi\Model\{Operation, Response};
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationCountryOptionOutput;
use Organization\Presentation\Api\Operation\OrganizationOperations;
use Organization\Presentation\Api\Provider\Organization\ListOrganizationCountriesProvider;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Resource OrganizationCountryResource.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'OrganizationCountry',
  routePrefix: '/organizations',
  description: 'Reference data for supported countries in organization legal profiles.',
  operations: [
    new GetCollection(
      name: OrganizationOperations::LIST_ORGANIZATION_COUNTRIES,
      uriTemplate: '/countries',
      input: false,
      output: OrganizationCountryOptionOutput::class,
      provider: ListOrganizationCountriesProvider::class,
      normalizationContext: ['groups' => [OrganizationSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      cacheHeaders: ['max_age' => 3600, 'shared_max_age' => 0],
      openapi: new Operation(
        tags: ['Organization'],
        summary: 'List supported countries',
        description: 'Returns the list of countries supported for organization legal profiles, with ISO code, display name, and flag image URL.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Country list retrieved'),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(description: 'Authentication required'),
        ],
      ),
    ),
  ],
)]
final class OrganizationCountryResource
{
}
