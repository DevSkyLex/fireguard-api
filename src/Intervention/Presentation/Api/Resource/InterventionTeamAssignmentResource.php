<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Post};
use ApiPlatform\OpenApi\Model\{Operation, Response};
use Intervention\Presentation\Api\Dto\Input\AssignInterventionTeamInput;
use Intervention\Presentation\Api\Dto\Output\InterventionOutput;
use Intervention\Presentation\Api\Processor\Assignment\AssignTeamToInterventionProcessor;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Resource InterventionTeamAssignmentResource.
 *
 * Snapshot-expands an organization team's active members into an
 * intervention's participants list (draft-only, requires
 * organization.interventions.plan). Kept as a dedicated resource file to
 * avoid growing the large {@see InterventionResource}.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'InterventionTeamAssignment',
  operations: [
    new Post(
      uriTemplate: '/interventions/{id}/team-assignments',
      input: AssignInterventionTeamInput::class,
      output: InterventionOutput::class,
      processor: AssignTeamToInterventionProcessor::class,
      status: HttpResponse::HTTP_OK,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Interventions'],
        summary: 'Assign a team to an intervention',
        description: 'Snapshot-expands the CURRENT active members of an organization team into the intervention participants list (union, deduped). Draft-only: requires organization.interventions.plan and is rejected once the intervention leaves draft.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Team assigned; participants updated'),
          HttpResponse::HTTP_UNPROCESSABLE_ENTITY => new Response(description: 'The team has no active members to assign'),
          HttpResponse::HTTP_CONFLICT => new Response(description: 'The intervention has left the draft planning stage'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Intervention not found'),
        ],
      ),
    ),
  ],
)]
final class InterventionTeamAssignmentResource
{
}
