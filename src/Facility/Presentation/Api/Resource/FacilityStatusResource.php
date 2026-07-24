<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, GetCollection};
use ApiPlatform\OpenApi\Model\Operation;
use Facility\Presentation\Api\Dto\Output\Facility\FacilityStatusOptionOutput;
use Facility\Presentation\Api\Operation\FacilityOperations;
use Facility\Presentation\Api\Provider\Facility\ListFacilityStatusesProvider;
use Facility\Presentation\Api\Serialization\FacilitySerializationGroup;

#[ApiResource(
  shortName: 'FacilityStatus',
  routePrefix: '/facilities',
  description: 'Reference data for facility statuses.',
  operations: [
    new GetCollection(
      name: FacilityOperations::LIST_FACILITY_STATUSES,
      uriTemplate: '/statuses',
      input: false,
      output: FacilityStatusOptionOutput::class,
      provider: ListFacilityStatusesProvider::class,
      normalizationContext: ['groups' => [FacilitySerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      cacheHeaders: ['max_age' => 3600, 'shared_max_age' => 0],
      openapi: new Operation(
        tags: ['Facility'],
        summary: 'List facility statuses',
        description: 'Returns facility statuses for filters and UI selects.',
      ),
    ),
  ],
)]
final class FacilityStatusResource
{
}
