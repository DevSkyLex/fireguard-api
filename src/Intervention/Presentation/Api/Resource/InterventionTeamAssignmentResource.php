<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Post};
use ApiPlatform\OpenApi\Model\{Operation, Parameter, Response};
use Intervention\Presentation\Api\Dto\Input\AssignInterventionTeamInput;
use Intervention\Presentation\Api\Dto\Output\InterventionOutput;
use Intervention\Presentation\Api\Operation\InterventionOperations;
use Intervention\Presentation\Api\Processor\Assignment\AssignTeamToInterventionProcessor;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Resource InterventionTeamAssignmentResource.
 *
 * Snapshot-expands an organization team's active members into an
 * intervention's participants list (requires
 * organization.interventions.plan). Participants follow the schedule
 * mutability guard {@see \Intervention\Domain\Model\Intervention\Intervention}
 * enforces, not a draft-only freeze: the assignment stays possible through
 * `planned`, `in_progress` and `changes_requested`, and is refused only once
 * the intervention is `submitted` (frozen under review) or `published` /
 * `abandoned` (immutable). Kept as a dedicated resource file to avoid growing
 * the large {@see InterventionResource}.
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
      name: InterventionOperations::ASSIGN_INTERVENTION_TEAM,
      uriTemplate: '/interventions/{id}/team-assignments',
      input: AssignInterventionTeamInput::class,
      output: InterventionOutput::class,
      processor: AssignTeamToInterventionProcessor::class,
      status: HttpResponse::HTTP_OK,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Interventions'],
        summary: 'Assign a team to an intervention',
        description: 'Snapshot-expands the CURRENT active members of an organization team into the intervention participants list (union, deduped). Requires organization.interventions.plan and an If-Match: "revision-N" header, since it writes participants through the same optimistic-concurrency path a manual edit uses. Participants stay assignable while the intervention is draft, planned, in_progress or changes_requested; the assignment is refused once it is submitted (frozen under review — withdraw it first) or published/abandoned (immutable). A teamId that does not exist in the caller\'s organization answers 404; 422 means the team exists but has no active members.',
        parameters: [
          new Parameter(
            name: 'If-Match',
            in: 'header',
            description: 'The intervention revision being mutated, as `"revision-N"`.',
            required: true,
            schema: ['type' => 'string'],
          ),
        ],
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Team assigned; participants updated'),
          HttpResponse::HTTP_UNPROCESSABLE_ENTITY => new Response(description: 'The team has no active members to assign'),
          HttpResponse::HTTP_PRECONDITION_FAILED => new Response(description: 'The intervention revision is stale'),
          HttpResponse::HTTP_PRECONDITION_REQUIRED => new Response(description: 'If-Match header is required'),
          HttpResponse::HTTP_CONFLICT => new Response(description: 'The intervention is frozen: submitted (withdraw it to replan), published or abandoned'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Intervention or team not found, or outside the caller\'s organization'),
        ],
      ),
    ),
  ],
)]
final class InterventionTeamAssignmentResource
{
}
