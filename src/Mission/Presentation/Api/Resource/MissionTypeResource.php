<?php

declare(strict_types=1);

namespace Mission\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, GetCollection};
use Mission\Presentation\Api\Dto\Output\MissionTypeOutput;
use Mission\Presentation\Api\Provider\MissionTypeProvider;

/**
 * Resource MissionTypeResource.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'MissionType',
  operations: [
    new GetCollection(uriTemplate: '/mission-types', output: MissionTypeOutput::class, provider: MissionTypeProvider::class, paginationEnabled: false, security: "is_granted('ROLE_USER')"),
  ],
)]
final class MissionTypeResource
{
}
