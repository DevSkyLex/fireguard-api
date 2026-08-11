<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Delete, Get, GetCollection, Post};
use ApiPlatform\OpenApi\Model\{Operation, Response};
use Organization\Presentation\Api\Dto\Input\Organization\{AddOrganizationMemberInput, RemoveOrganizationMembersInput};
use Organization\Presentation\Api\Dto\Output\Organization\{OrganizationMemberOutput, RemoveOrganizationMembersOutput};
use Organization\Presentation\Api\Operation\OrganizationOperations;
use Organization\Presentation\Api\Processor\Organization\{
  AddOrganizationMemberProcessor,
  LeaveOrganizationProcessor,
  ReactivateOrganizationMemberProcessor,
  RemoveOrganizationMemberProcessor,
  RemoveOrganizationMembersProcessor
};
use Organization\Presentation\Api\Provider\Organization\{GetOrganizationMemberProvider, ListOrganizationMembersProvider};
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Resource OrganizationMemberResource.
 *
 * @category Resource
 *
 * @version 1.1.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'OrganizationMember',
  routePrefix: '/organizations',
  description: 'Organization member management.',
  operations: [
    new Post(
      name: OrganizationOperations::ADD_ORGANIZATION_MEMBER,
      uriTemplate: '/{organizationId}/members',
      input: AddOrganizationMemberInput::class,
      output: OrganizationMemberOutput::class,
      processor: AddOrganizationMemberProcessor::class,
      denormalizationContext: ['groups' => [OrganizationSerializationGroup::WRITE]],
      normalizationContext: ['groups' => [OrganizationSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Organization Members'],
        summary: 'Add Organization member',
        description: 'Adds an existing user to a Organization and assigns one or more roles.',
      ),
    ),
    new GetCollection(
      name: OrganizationOperations::LIST_ORGANIZATION_MEMBERS,
      uriTemplate: '/{organizationId}/members',
      input: false,
      output: OrganizationMemberOutput::class,
      provider: ListOrganizationMembersProvider::class,
      paginationEnabled: true,
      paginationClientItemsPerPage: true,
      paginationItemsPerPage: 30,
      normalizationContext: ['groups' => [OrganizationSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Organization Members'],
        summary: 'List Organization members',
        description: 'Lists Organization members and assigned roles. Supports `search` (matched against the member\'s user identifier), `status` (`active`, `inactive`, or `all`), `roleId`, and `order[joinedAt|displayName]=asc|desc` (default `order[joinedAt]=asc`).',
      ),
    ),
    new Get(
      name: OrganizationOperations::GET_ORGANIZATION_MEMBER,
      uriTemplate: '/{organizationId}/members/{memberId}',
      requirements: ['memberId' => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}'],
      input: false,
      output: OrganizationMemberOutput::class,
      provider: GetOrganizationMemberProvider::class,
      normalizationContext: ['groups' => [OrganizationSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Organization Members'],
        summary: 'Get Organization member',
        description: 'Resolves a single organization member by identifier.',
        responses: [
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Organization not found, or member not found in this organization'),
        ],
      ),
    ),
    new Post(
      name: OrganizationOperations::BATCH_REMOVE_ORGANIZATION_MEMBERS,
      uriTemplate: '/{organizationId}/members/batch-remove',
      input: RemoveOrganizationMembersInput::class,
      output: RemoveOrganizationMembersOutput::class,
      processor: RemoveOrganizationMembersProcessor::class,
      denormalizationContext: ['groups' => [OrganizationSerializationGroup::WRITE]],
      normalizationContext: ['groups' => [OrganizationSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Organization Members'],
        summary: 'Batch remove Organization members',
        description: 'Removes several members in one request, reporting removed and failed IDs.',
      ),
    ),
    new Post(
      name: OrganizationOperations::REACTIVATE_ORGANIZATION_MEMBER,
      uriTemplate: '/{organizationId}/members/{memberId}/reactivate',
      status: HttpResponse::HTTP_OK,
      requirements: ['memberId' => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}'],
      input: false,
      output: OrganizationMemberOutput::class,
      processor: ReactivateOrganizationMemberProcessor::class,
      normalizationContext: ['groups' => [OrganizationSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Organization Members'],
        summary: 'Reactivate Organization member',
        description: 'Reactivates a previously deactivated (removed) organization member. Subject to the same plan member cap as adding a new member.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Member reactivated'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Organization not found, or member not found in this organization'),
          HttpResponse::HTTP_CONFLICT => new Response(description: 'Organization is archived, member is already active, or the plan\'s member cap has been reached'),
        ],
      ),
    ),
    new Delete(
      name: OrganizationOperations::LEAVE_ORGANIZATION,
      uriTemplate: '/{organizationId}/members/me',
      read: false,
      input: false,
      output: false,
      processor: LeaveOrganizationProcessor::class,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Organization Members'],
        summary: 'Leave Organization',
        description: 'Deactivates the authenticated user\'s own membership (self-removal). The organization\'s current owner cannot leave — transfer ownership first via POST /organizations/{id}/transfer-ownership. Refused when leaving would strip the organization of its last active administrator.',
        responses: [
          HttpResponse::HTTP_NO_CONTENT => new Response(description: 'Membership deactivated'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Organization not found, or caller is not an active member'),
          HttpResponse::HTTP_CONFLICT => new Response(description: 'Caller is the organization\'s current owner, or is the last active administrator'),
        ],
      ),
    ),
    new Delete(
      name: OrganizationOperations::REMOVE_ORGANIZATION_MEMBER,
      uriTemplate: '/{organizationId}/members/{memberId}',
      requirements: ['memberId' => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}'],
      read: false,
      input: false,
      output: false,
      processor: RemoveOrganizationMemberProcessor::class,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Organization Members'],
        summary: 'Remove Organization member',
        description: 'Deactivates an organization member. The membership record is retained for audit purposes.',
        responses: [
          HttpResponse::HTTP_NO_CONTENT => new Response(description: 'Member removed'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Invalid identifier'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Organization or member not found'),
        ],
      ),
    ),
  ],
)]
final class OrganizationMemberResource
{
}
