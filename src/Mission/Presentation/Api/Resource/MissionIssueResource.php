<?php

declare(strict_types=1);

namespace Mission\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, GetCollection};
use Mission\Presentation\Api\Dto\Output\MissionIssueOutput;
use Mission\Presentation\Api\Provider\MissionIssueProvider;

/**
 * Resource MissionIssueResource.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'MissionIssue',
  operations: [
    new GetCollection(
      uriTemplate: '/missions/{id}/issues',
      output: MissionIssueOutput::class,
      provider: MissionIssueProvider::class,
      paginationEnabled: false,
      security: "is_granted('ROLE_USER')",
    ),
  ],
)]
final class MissionIssueResource
{
}
