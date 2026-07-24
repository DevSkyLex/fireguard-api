<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, GetCollection};
use ApiPlatform\OpenApi\Model\Operation;
use Facility\Presentation\Api\Dto\Output\Facility\FacilityTypeOptionOutput;
use Facility\Presentation\Api\Operation\FacilityOperations;
use Facility\Presentation\Api\Provider\Facility\ListFacilityTypesProvider;
use Facility\Presentation\Api\Serialization\FacilitySerializationGroup;

/**
 * Resource FacilityTypeResource.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'FacilityType',
  routePrefix: '/facilities',
  description: 'Reference data for facility types.',
  operations: [
    new GetCollection(
      name: FacilityOperations::LIST_FACILITY_TYPES,
      uriTemplate: '/types',
      input: false,
      output: FacilityTypeOptionOutput::class,
      provider: ListFacilityTypesProvider::class,
      normalizationContext: ['groups' => [FacilitySerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      cacheHeaders: ['max_age' => 3600, 'shared_max_age' => 0],
      openapi: new Operation(
        tags: ['Facility'],
        summary: 'List facility types',
        description: 'Returns facility types for UI selects.',
      ),
    ),
  ],
)]
final class FacilityTypeResource
{
}
