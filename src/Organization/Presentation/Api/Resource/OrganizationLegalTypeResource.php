<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, GetCollection};
use ApiPlatform\OpenApi\Model\{Operation, Parameter};
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationLegalTypeOptionOutput;
use Organization\Presentation\Api\Operation\OrganizationOperations;
use Organization\Presentation\Api\Provider\Organization\ListOrganizationLegalTypesProvider;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;

/**
 * Resource OrganizationLegalTypeResource.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'OrganizationLegalType',
  routePrefix: '/organizations',
  description: 'Reference data for organization legal types.',
  operations: [
    new GetCollection(
      name: OrganizationOperations::LIST_ORGANIZATION_LEGAL_TYPES,
      uriTemplate: '/legal-types',
      input: false,
      output: OrganizationLegalTypeOptionOutput::class,
      provider: ListOrganizationLegalTypesProvider::class,
      normalizationContext: ['groups' => [OrganizationSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Organization'],
        summary: 'List organization legal types',
        description: 'Returns legal types and related constraints for UI selects. Rules can be adapted with the optional countryCode query parameter.',
        parameters: [
          new Parameter(
            name: 'countryCode',
            in: 'query',
            required: false,
            description: 'ISO 3166-1 alpha-2 country code used to tailor legal requirements. Default: FR.',
            schema: ['type' => 'string', 'pattern' => '^[A-Za-z]{2}$', 'example' => 'FR'],
          ),
        ],
      ),
    ),
  ],
)]
final class OrganizationLegalTypeResource
{
}
