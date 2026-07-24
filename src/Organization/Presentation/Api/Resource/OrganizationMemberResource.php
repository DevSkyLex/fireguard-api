<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Delete, GetCollection, Post};
use ApiPlatform\OpenApi\Model\{Operation, Response};
use Organization\Presentation\Api\Dto\Input\Organization\{AddOrganizationMemberInput, RemoveOrganizationMembersInput};
use Organization\Presentation\Api\Dto\Output\Organization\{OrganizationMemberOutput, RemoveOrganizationMembersOutput};
use Organization\Presentation\Api\Operation\OrganizationOperations;
use Organization\Presentation\Api\Processor\Organization\{AddOrganizationMemberProcessor, RemoveOrganizationMemberProcessor, RemoveOrganizationMembersProcessor};
use Organization\Presentation\Api\Provider\Organization\ListOrganizationMembersProvider;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Resource OrganizationMemberResource.
 *
 * @category Resource
 *
 * @version 1.0.0
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
        description: 'Lists Organization members and assigned roles.',
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
    new Delete(
      name: OrganizationOperations::REMOVE_ORGANIZATION_MEMBER,
      uriTemplate: '/{organizationId}/members/{memberId}',
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
