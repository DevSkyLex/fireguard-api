<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, GetCollection, Post};
use ApiPlatform\OpenApi\Model\Operation;
use Organization\Presentation\Api\Dto\Input\Organization\AddOrganizationMemberInput;
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationMemberOutput;
use Organization\Presentation\Api\Operation\OrganizationOperations;
use Organization\Presentation\Api\Processor\Organization\AddOrganizationMemberProcessor;
use Organization\Presentation\Api\Provider\Organization\ListOrganizationMembersProvider;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;

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
      normalizationContext: ['groups' => [OrganizationSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Organization Members'],
        summary: 'List Organization members',
        description: 'Lists Organization members and assigned roles.',
      ),
    ),
  ],
)]
final class OrganizationMemberResource
{
}
