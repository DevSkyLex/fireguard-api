<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, GetCollection, Patch, Post};
use ApiPlatform\OpenApi\Model\Operation;
use Organization\Presentation\Api\Dto\Input\Organization\{AssignOrganizationRoleInput, CreateOrganizationRoleInput, UpdateOrganizationRoleInput};
use Organization\Presentation\Api\Dto\Output\Organization\{OrganizationMemberOutput, OrganizationRoleOutput};
use Organization\Presentation\Api\Operation\OrganizationOperations;
use Organization\Presentation\Api\Processor\Organization\{AssignOrganizationRoleToMemberProcessor, CreateOrganizationRoleProcessor, UpdateOrganizationRoleProcessor};
use Organization\Presentation\Api\Provider\Organization\ListOrganizationRolesProvider;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;

/**
 * Resource OrganizationRoleResource.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'OrganizationRole',
  routePrefix: '/organizations',
  description: 'Organization RBAC role management.',
  operations: [
    new Post(
      name: OrganizationOperations::CREATE_ORGANIZATION_ROLE,
      uriTemplate: '/{organizationId}/roles',
      input: CreateOrganizationRoleInput::class,
      output: OrganizationRoleOutput::class,
      processor: CreateOrganizationRoleProcessor::class,
      denormalizationContext: ['groups' => [OrganizationSerializationGroup::WRITE]],
      normalizationContext: ['groups' => [OrganizationSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Organization Roles'],
        summary: 'Create Organization role',
        description: 'Creates a custom role inside a Organization with explicit permissions.',
      ),
    ),
    new GetCollection(
      name: OrganizationOperations::LIST_ORGANIZATION_ROLES,
      uriTemplate: '/{organizationId}/roles',
      input: false,
      output: OrganizationRoleOutput::class,
      provider: ListOrganizationRolesProvider::class,
      normalizationContext: ['groups' => [OrganizationSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Organization Roles'],
        summary: 'List Organization roles',
        description: 'Lists all roles defined for a Organization.',
      ),
    ),
    new Patch(
      name: OrganizationOperations::UPDATE_ORGANIZATION_ROLE,
      uriTemplate: '/{organizationId}/roles/{roleId}',
      input: UpdateOrganizationRoleInput::class,
      output: OrganizationRoleOutput::class,
      processor: UpdateOrganizationRoleProcessor::class,
      denormalizationContext: ['groups' => [OrganizationSerializationGroup::WRITE]],
      normalizationContext: ['groups' => [OrganizationSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Organization Roles'],
        summary: 'Update Organization role permissions',
        description: 'Updates the permissions assigned to a custom organization role.',
      ),
    ),
    new Post(
      name: OrganizationOperations::ASSIGN_ORGANIZATION_ROLE_TO_MEMBER,
      uriTemplate: '/{organizationId}/members/{memberId}/roles',
      input: AssignOrganizationRoleInput::class,
      output: OrganizationMemberOutput::class,
      processor: AssignOrganizationRoleToMemberProcessor::class,
      denormalizationContext: ['groups' => [OrganizationSerializationGroup::WRITE]],
      normalizationContext: ['groups' => [OrganizationSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Organization Roles'],
        summary: 'Assign role to member',
        description: 'Assigns an existing Organization role to an existing Organization member.',
      ),
    ),
  ],
)]
final class OrganizationRoleResource
{
}
