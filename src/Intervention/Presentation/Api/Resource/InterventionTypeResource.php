<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, GetCollection};
use Intervention\Presentation\Api\Dto\Output\InterventionTypeOutput;
use Intervention\Presentation\Api\Operation\InterventionOperations;
use Intervention\Presentation\Api\Provider\InterventionTypeProvider;

/**
 * Resource InterventionTypeResource.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'InterventionType',
  operations: [
    new GetCollection(name: InterventionOperations::LIST_INTERVENTION_TYPES, uriTemplate: '/intervention-types', output: InterventionTypeOutput::class, provider: InterventionTypeProvider::class, paginationEnabled: false, security: "is_granted('ROLE_USER')"),
  ],
)]
final class InterventionTypeResource
{
}
