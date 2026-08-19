<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, GetCollection};
use Intervention\Presentation\Api\Dto\Output\InterventionIssueOutput;
use Intervention\Presentation\Api\Operation\InterventionOperations;
use Intervention\Presentation\Api\Provider\InterventionIssueProvider;

/**
 * Resource InterventionIssueResource.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'InterventionIssue',
  operations: [
    new GetCollection(
      name: InterventionOperations::LIST_INTERVENTION_ISSUES,
      uriTemplate: '/interventions/{id}/issues',
      output: InterventionIssueOutput::class,
      provider: InterventionIssueProvider::class,
      paginationEnabled: false,
      security: "is_granted('ROLE_USER')",
    ),
  ],
)]
final class InterventionIssueResource
{
}
