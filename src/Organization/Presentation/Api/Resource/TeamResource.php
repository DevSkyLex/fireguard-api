<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Delete, Get, GetCollection, Patch, Post};
use ApiPlatform\OpenApi\Model\{Operation, Response};
use Organization\Presentation\Api\Dto\Input\Team\{AddTeamMemberInput, CreateTeamInput, UpdateTeamInput};
use Organization\Presentation\Api\Dto\Output\Team\{TeamMemberOutput, TeamOutput};
use Organization\Presentation\Api\Operation\OrganizationOperations;
use Organization\Presentation\Api\Processor\Team\{AddTeamMemberProcessor, CreateTeamProcessor, DeleteTeamProcessor, RemoveTeamMemberProcessor, UpdateTeamProcessor};
use Organization\Presentation\Api\Provider\Team\{GetTeamProvider, ListTeamMembersProvider, ListTeamsProvider};
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Resource TeamResource.
 *
 * Organization-scoped team management: groups of members mirroring
 * {@see OrganizationRoleResource}'s endpoint/permission shape.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'Team',
  routePrefix: '/organizations',
  description: 'Organization team management.',
  operations: [
    new Post(
      name: OrganizationOperations::CREATE_TEAM,
      uriTemplate: '/{organizationId}/teams',
      input: CreateTeamInput::class,
      output: TeamOutput::class,
      processor: CreateTeamProcessor::class,
      denormalizationContext: ['groups' => [OrganizationSerializationGroup::WRITE]],
      normalizationContext: ['groups' => [OrganizationSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Teams'],
        summary: 'Create team',
        description: 'Creates a team inside an organization. Requires organization.teams.write.',
        responses: [
          HttpResponse::HTTP_CREATED => new Response(description: 'Team created'),
          HttpResponse::HTTP_CONFLICT => new Response(description: 'A team with this name already exists in the organization'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Organization not found'),
        ],
      ),
    ),
    new GetCollection(
      name: OrganizationOperations::LIST_TEAMS,
      uriTemplate: '/{organizationId}/teams',
      input: false,
      output: TeamOutput::class,
      provider: ListTeamsProvider::class,
      normalizationContext: ['groups' => [OrganizationSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Teams'],
        summary: 'List teams',
        description: 'Lists all teams defined for an organization. Requires organization.teams.read.',
      ),
    ),
    new Get(
      name: OrganizationOperations::GET_TEAM,
      uriTemplate: '/{organizationId}/teams/{teamId}',
      output: TeamOutput::class,
      provider: GetTeamProvider::class,
      normalizationContext: ['groups' => [OrganizationSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Teams'],
        summary: 'Get team',
        description: 'Returns a single team by identifier. Requires organization.teams.read.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Team retrieved'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Organization or team not found'),
        ],
      ),
    ),
    new Patch(
      name: OrganizationOperations::UPDATE_TEAM,
      uriTemplate: '/{organizationId}/teams/{teamId}',
      // `read: false`: this operation had a processor but no provider and no
      // explicit `read: false`, so API Platform's default pre-read step
      // (`read: true`) tried to resolve the current resource state through a
      // generic provider that cannot resolve this non-Doctrine DTO — every
      // authenticated PATCH unconditionally 404'd before the processor ever
      // ran. The sibling `Delete` operations below, and every other mutating
      // organization operation with a processor, already set `read: false`
      // for the same reason.
      read: false,
      input: UpdateTeamInput::class,
      output: TeamOutput::class,
      processor: UpdateTeamProcessor::class,
      denormalizationContext: ['groups' => [OrganizationSerializationGroup::WRITE]],
      normalizationContext: ['groups' => [OrganizationSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Teams'],
        summary: 'Update team',
        description: 'Renames or redescribes a team. Requires organization.teams.write.',
      ),
    ),
    new Delete(
      name: OrganizationOperations::DELETE_TEAM,
      uriTemplate: '/{organizationId}/teams/{teamId}',
      read: false,
      input: false,
      output: false,
      processor: DeleteTeamProcessor::class,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Teams'],
        summary: 'Delete team',
        description: 'Permanently deletes a team. All team member assignments are removed. Requires organization.teams.manage.',
        responses: [
          HttpResponse::HTTP_NO_CONTENT => new Response(description: 'Team deleted'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Organization or team not found'),
        ],
      ),
    ),
    new Post(
      name: OrganizationOperations::ADD_TEAM_MEMBER,
      uriTemplate: '/{organizationId}/teams/{teamId}/members',
      input: AddTeamMemberInput::class,
      output: TeamMemberOutput::class,
      processor: AddTeamMemberProcessor::class,
      denormalizationContext: ['groups' => [OrganizationSerializationGroup::WRITE]],
      normalizationContext: ['groups' => [OrganizationSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Teams'],
        summary: 'Add team member',
        description: 'Adds an active organization member to a team. Requires organization.teams.write.',
        responses: [
          HttpResponse::HTTP_CREATED => new Response(description: 'Member added to the team'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Member is not active in this organization, or already part of the team'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Organization, team, or member not found'),
        ],
      ),
    ),
    new Delete(
      name: OrganizationOperations::REMOVE_TEAM_MEMBER,
      uriTemplate: '/{organizationId}/teams/{teamId}/members/{memberId}',
      read: false,
      input: false,
      output: false,
      processor: RemoveTeamMemberProcessor::class,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Teams'],
        summary: 'Remove team member',
        description: 'Removes a member from a team. Requires organization.teams.write.',
        responses: [
          HttpResponse::HTTP_NO_CONTENT => new Response(description: 'Member removed from the team'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Organization, team, or membership not found'),
        ],
      ),
    ),
    new GetCollection(
      name: OrganizationOperations::LIST_TEAM_MEMBERS,
      uriTemplate: '/{organizationId}/teams/{teamId}/members',
      input: false,
      output: TeamMemberOutput::class,
      provider: ListTeamMembersProvider::class,
      normalizationContext: ['groups' => [OrganizationSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Teams'],
        summary: 'List team members',
        description: 'Lists members assigned to a team. Requires organization.teams.read.',
      ),
    ),
  ],
)]
final class TeamResource
{
}
