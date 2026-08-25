<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Delete, Get, GetCollection, Patch, Post, Put};
use ApiPlatform\OpenApi\Model\{Operation, Response};
use Organization\Presentation\Api\Dto\Input\Organization\{AssignOrganizationRoleInput, CreateOrganizationRoleInput, SetOrganizationMemberRolesInput, UpdateOrganizationRoleInput};
use Organization\Presentation\Api\Dto\Output\Organization\{OrganizationMemberOutput, OrganizationRoleOutput};
use Organization\Presentation\Api\Operation\OrganizationOperations;
use Organization\Presentation\Api\Processor\Organization\{AssignOrganizationRoleToMemberProcessor, CreateOrganizationRoleProcessor, DeleteOrganizationRoleProcessor, RemoveOrganizationRoleFromMemberProcessor, SetOrganizationMemberRolesProcessor, UpdateOrganizationRoleProcessor};
use Organization\Presentation\Api\Provider\Organization\{GetOrganizationRoleProvider, ListOrganizationRolesProvider};
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Resource OrganizationRoleResource.
 *
 * @category Resource
 *
 * @version 1.2.0
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
      paginationEnabled: true,
      paginationClientItemsPerPage: true,
      paginationMaximumItemsPerPage: 100,
      paginationItemsPerPage: 30,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Organization Roles'],
        summary: 'List Organization roles',
        description: 'Lists all roles defined for a Organization. Real pagination (`page`/`itemsPerPage`, default 30); `totalItems` reflects the count AFTER search filtering. Supports `search` (matched against the role name) and `order[name|isSystem|createdAt]=asc|desc` (default `order[name]=asc`).',
      ),
    ),
    new Get(
      name: OrganizationOperations::GET_ORGANIZATION_ROLE,
      uriTemplate: '/{organizationId}/roles/{roleId}',
      requirements: ['roleId' => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}'],
      input: false,
      output: OrganizationRoleOutput::class,
      provider: GetOrganizationRoleProvider::class,
      normalizationContext: ['groups' => [OrganizationSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Organization Roles'],
        summary: 'Get Organization role',
        description: 'Returns a single organization role, including memberCount (the number of ACTIVE members currently assigned). Requires the organization.roles.read permission.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Role retrieved'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Organization or role not found, or the role belongs to another organization'),
        ],
      ),
    ),
    new Patch(
      name: OrganizationOperations::UPDATE_ORGANIZATION_ROLE,
      uriTemplate: '/{organizationId}/roles/{roleId}',
      // `read: false`: this operation had no provider and no explicit
      // `read: false`, so API Platform's default pre-read step (`read: true`)
      // tried to resolve the current resource state through a generic
      // provider that cannot resolve this non-Doctrine DTO — every
      // authenticated PATCH unconditionally 404'd before the processor ever
      // ran. `DeleteOrganizationRoleProcessor`'s sibling `Delete` operation,
      // and every other mutating organization operation with a processor
      // (`UpdateOrganizationSettings`, `ChangeOrganizationPlan`, …), already
      // set `read: false` for the same reason.
      read: false,
      input: UpdateOrganizationRoleInput::class,
      output: OrganizationRoleOutput::class,
      processor: UpdateOrganizationRoleProcessor::class,
      denormalizationContext: ['groups' => [OrganizationSerializationGroup::WRITE]],
      normalizationContext: ['groups' => [OrganizationSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Organization Roles'],
        summary: 'Update Organization role permissions',
        description: 'Updates the permissions assigned to a custom organization role, and optionally renames it.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Role updated'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Invalid payload, or the role is a system role'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions, or granting a permission the caller does not hold'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Organization or role not found, or the role belongs to another organization'),
          HttpResponse::HTTP_CONFLICT => new Response(description: 'The change would leave the organization without an administrator'),
        ],
      ),
    ),
    new Delete(
      name: OrganizationOperations::DELETE_ORGANIZATION_ROLE,
      uriTemplate: '/{organizationId}/roles/{roleId}',
      read: false,
      input: false,
      output: false,
      processor: DeleteOrganizationRoleProcessor::class,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Organization Roles'],
        summary: 'Delete Organization role',
        description: 'Permanently deletes a custom role. All member role assignments for this role are removed. System roles cannot be deleted.',
        responses: [
          HttpResponse::HTTP_NO_CONTENT => new Response(description: 'Role deleted'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Invalid identifier or system role'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Organization or role not found'),
        ],
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
    new Put(
      name: OrganizationOperations::SET_ORGANIZATION_MEMBER_ROLES,
      uriTemplate: '/{organizationId}/members/{memberId}/roles',
      requirements: ['memberId' => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}'],
      read: false,
      input: SetOrganizationMemberRolesInput::class,
      output: OrganizationMemberOutput::class,
      processor: SetOrganizationMemberRolesProcessor::class,
      denormalizationContext: ['groups' => [OrganizationSerializationGroup::WRITE]],
      normalizationContext: ['groups' => [OrganizationSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Organization Roles'],
        summary: 'Replace member roles',
        description: 'Replaces the member\'s entire role set in one call. Roles being granted go through the privilege-escalation guard; roles being revoked go through the last-administrator lockout guard.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Member roles replaced'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions, or attempting to grant a permission the caller does not hold'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Organization, member, or one of the requested roles not found'),
          HttpResponse::HTTP_CONFLICT => new Response(description: 'Would strip the organization of its last administrator'),
        ],
      ),
    ),
    new Delete(
      name: OrganizationOperations::REMOVE_ORGANIZATION_ROLE_FROM_MEMBER,
      uriTemplate: '/{organizationId}/members/{memberId}/roles/{roleId}',
      read: false,
      input: false,
      output: false,
      processor: RemoveOrganizationRoleFromMemberProcessor::class,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Organization Roles'],
        summary: 'Remove role from member',
        description: 'Removes a role assignment from an organization member.',
        responses: [
          HttpResponse::HTTP_NO_CONTENT => new Response(description: 'Role removed from member'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Invalid identifier'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Organization, member, or role not found'),
        ],
      ),
    ),
  ],
)]
final class OrganizationRoleResource
{
}
