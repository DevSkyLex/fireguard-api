<?php

declare(strict_types=1);

namespace Authorization\Presentation\Api\Resource;

use ApiPlatform\Metadata\{
  ApiResource,
  Delete,
  Get,
  GetCollection,
  Link,
  Patch,
  Post
};
use ApiPlatform\OpenApi\Model\{
  Operation,
  Parameter,
  Response
};
use ArrayObject;
use Authorization\Infrastructure\Persistence\Doctrine\Record\{PermissionRecord, RoleRecord};
use Authorization\Presentation\Api\Dto\Input\Role\{AddPermissionInput, RoleInput};
use Authorization\Presentation\Api\Dto\Output\Role\RoleOutput;
use Authorization\Presentation\Api\Operation\AuthorizationOperations;
use Authorization\Presentation\Api\Processor\Role\{AddPermissionToRoleProcessor, CreateRoleProcessor, DeleteRoleProcessor, RemovePermissionFromRoleProcessor, UpdateRoleProcessor};
use Authorization\Presentation\Api\Provider\Role\{GetRoleProvider, ListRolesProvider};
use Authorization\Presentation\Api\Serialization\RoleSerializationGroup;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Resource RoleResource.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'Role',
  description: 'Role-Based Access Control (RBAC) role management. Roles group permissions together.',
  operations: [
    new GetCollection(
      name: AuthorizationOperations::ROLE_LIST,
      uriTemplate: '/roles',
      input: false,
      output: RoleOutput::class,
      provider: ListRolesProvider::class,
      normalizationContext: ['groups' => [RoleSerializationGroup::READ]],
      security: "is_granted('roles.read')",
      openapi: new Operation(
        tags: ['Authorization - Roles'],
        summary: 'List all roles',
        description: 'Returns a paginated list of all roles with their associated permissions. Requires roles.read permission.',
        security: [['bearerAuth' => []]],
        parameters: [
          new Parameter(name: 'isSystem', in: 'query', required: false, description: 'Filter by system role flag', schema: ['type' => 'boolean']),
        ],
        responses: [
          HttpResponse::HTTP_OK => new Response(
            description: 'List of roles retrieved successfully',
          ),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(
            description: 'Authentication required - missing or invalid access token',
          ),
          HttpResponse::HTTP_FORBIDDEN => new Response(
            description: 'Insufficient permissions - roles.read required',
          ),
        ],
      ),
    ),
    new Get(
      name: AuthorizationOperations::ROLE_GET,
      uriTemplate: '/roles/{id}',
      input: false,
      output: RoleOutput::class,
      provider: GetRoleProvider::class,
      normalizationContext: ['groups' => [RoleSerializationGroup::READ]],
      security: "is_granted('roles.read')",
      openapi: new Operation(
        tags: ['Authorization - Roles'],
        summary: 'Get role details',
        description: 'Returns details of a specific role including all its assigned permissions. Requires roles.read permission.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_OK => new Response(
            description: 'Role details retrieved successfully',
            links: new ArrayObject([
              'UpdateRole' => [
                'operationId' => AuthorizationOperations::ROLE_UPDATE,
                'description' => 'Update this role',
                'parameters' => [
                  'id' => '$response.body#/id',
                ],
              ],
              'DeleteRole' => [
                'operationId' => AuthorizationOperations::ROLE_DELETE,
                'description' => 'Delete this role (if not a system role)',
                'parameters' => [
                  'id' => '$response.body#/id',
                ],
              ],
              'AddPermission' => [
                'operationId' => AuthorizationOperations::ROLE_ADD_PERMISSION,
                'description' => 'Add a permission to this role',
                'parameters' => [
                  'id' => '$response.body#/id',
                ],
              ],
            ]),
          ),
          HttpResponse::HTTP_NOT_FOUND => new Response(
            description: 'Role not found',
          ),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(
            description: 'Authentication required - missing or invalid access token',
          ),
          HttpResponse::HTTP_FORBIDDEN => new Response(
            description: 'Insufficient permissions - roles.read required',
          ),
        ],
      ),
    ),
    new Post(
      name: AuthorizationOperations::ROLE_CREATE,
      uriTemplate: '/roles',
      input: RoleInput::class,
      output: RoleOutput::class,
      processor: CreateRoleProcessor::class,
      normalizationContext: ['groups' => [RoleSerializationGroup::READ]],
      denormalizationContext: ['groups' => [RoleSerializationGroup::WRITE]],
      security: "is_granted('roles.create')",
      openapi: new Operation(
        tags: ['Authorization - Roles'],
        summary: 'Create a new role',
        description: 'Creates a new role with optional permissions. Role names must be unique and follow the naming convention (lowercase, starts with letter). Requires roles.create permission.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_CREATED => new Response(
            description: 'Role created successfully',
            links: new ArrayObject([
              'GetRole' => [
                'operationId' => AuthorizationOperations::ROLE_GET,
                'description' => 'Get the created role details',
                'parameters' => [
                  'id' => '$response.body#/id',
                ],
              ],
              'AddPermission' => [
                'operationId' => AuthorizationOperations::ROLE_ADD_PERMISSION,
                'description' => 'Add a permission to this role',
                'parameters' => [
                  'id' => '$response.body#/id',
                ],
              ],
            ]),
          ),
          HttpResponse::HTTP_BAD_REQUEST => new Response(
            description: 'Invalid request - validation failed',
          ),
          HttpResponse::HTTP_CONFLICT => new Response(
            description: 'Role name already exists',
          ),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(
            description: 'Authentication required - missing or invalid access token',
          ),
          HttpResponse::HTTP_FORBIDDEN => new Response(
            description: 'Insufficient permissions - roles.create required',
          ),
        ],
      ),
    ),
    new Patch(
      name: AuthorizationOperations::ROLE_UPDATE,
      uriTemplate: '/roles/{id}',
      input: RoleInput::class,
      output: RoleOutput::class,
      processor: UpdateRoleProcessor::class,
      normalizationContext: ['groups' => [RoleSerializationGroup::READ]],
      denormalizationContext: ['groups' => [RoleSerializationGroup::UPDATE]],
      security: "is_granted('roles.update')",
      openapi: new Operation(
        tags: ['Authorization - Roles'],
        summary: 'Update a role',
        description: 'Updates the description or permissions of an existing role. Role name cannot be changed after creation. Requires roles.update permission.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_OK => new Response(
            description: 'Role updated successfully',
            links: new ArrayObject([
              'GetRole' => [
                'operationId' => AuthorizationOperations::ROLE_GET,
                'description' => 'Get the updated role details',
                'parameters' => [
                  'id' => '$response.body#/id',
                ],
              ],
            ]),
          ),
          HttpResponse::HTTP_BAD_REQUEST => new Response(
            description: 'Invalid request - validation failed',
          ),
          HttpResponse::HTTP_NOT_FOUND => new Response(
            description: 'Role not found',
          ),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(
            description: 'Authentication required - missing or invalid access token',
          ),
          HttpResponse::HTTP_FORBIDDEN => new Response(
            description: 'Insufficient permissions - roles.update required',
          ),
        ],
      ),
    ),
    new Delete(
      name: AuthorizationOperations::ROLE_DELETE,
      uriTemplate: '/roles/{id}',
      input: false,
      output: false,
      processor: DeleteRoleProcessor::class,
      security: "is_granted('roles.delete')",
      openapi: new Operation(
        tags: ['Authorization - Roles'],
        summary: 'Delete a role',
        description: 'Permanently deletes a role. System roles cannot be deleted. Users assigned to this role will lose these permissions. Requires roles.delete permission.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_NO_CONTENT => new Response(
            description: 'Role deleted successfully',
          ),
          HttpResponse::HTTP_NOT_FOUND => new Response(
            description: 'Role not found',
          ),
          HttpResponse::HTTP_CONFLICT => new Response(
            description: 'Cannot delete system role',
          ),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(
            description: 'Authentication required - missing or invalid access token',
          ),
          HttpResponse::HTTP_FORBIDDEN => new Response(
            description: 'Insufficient permissions - roles.delete required',
          ),
        ],
      ),
    ),
    new Post(
      name: AuthorizationOperations::ROLE_ADD_PERMISSION,
      uriTemplate: '/roles/{roleId}/permissions',
      status: HttpResponse::HTTP_OK,
      uriVariables: [
        'roleId' => new Link(
          fromClass: RoleRecord::class,
          identifiers: ['id'],
        ),
      ],
      input: AddPermissionInput::class,
      output: RoleOutput::class,
      processor: AddPermissionToRoleProcessor::class,
      normalizationContext: ['groups' => [RoleSerializationGroup::READ]],
      security: "is_granted('roles.update')",
      openapi: new Operation(
        tags: ['Authorization - Roles'],
        summary: 'Add permission to role',
        description: 'Adds a permission to an existing role. If the permission is already assigned, no error is returned. Requires roles.update permission.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_OK => new Response(
            description: 'Permission added successfully - role returned with updated permissions',
            links: new ArrayObject([
              'GetRole' => [
                'operationId' => AuthorizationOperations::ROLE_GET,
                'description' => 'Get the role details',
                'parameters' => [
                  'id' => '$response.body#/id',
                ],
              ],
              'RemovePermission' => [
                'operationId' => AuthorizationOperations::ROLE_REMOVE_PERMISSION,
                'description' => 'Remove a permission from this role',
                'parameters' => [
                  'id' => '$response.body#/id',
                ],
              ],
            ]),
          ),
          HttpResponse::HTTP_BAD_REQUEST => new Response(
            description: 'Invalid request - permission ID invalid',
          ),
          HttpResponse::HTTP_NOT_FOUND => new Response(
            description: 'Role or permission not found',
          ),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(
            description: 'Authentication required - missing or invalid access token',
          ),
          HttpResponse::HTTP_FORBIDDEN => new Response(
            description: 'Insufficient permissions - roles.update required',
          ),
        ],
      ),
    ),
    new Delete(
      name: AuthorizationOperations::ROLE_REMOVE_PERMISSION,
      uriTemplate: '/roles/{roleId}/permissions/{permissionId}',
      uriVariables: [
        'roleId' => new Link(
          fromClass: RoleRecord::class,
          identifiers: ['id'],
        ),
        'permissionId' => new Link(
          fromClass: PermissionRecord::class,
          identifiers: ['id'],
        ),
      ],
      input: false,
      output: RoleOutput::class,
      processor: RemovePermissionFromRoleProcessor::class,
      normalizationContext: ['groups' => [RoleSerializationGroup::READ]],
      security: "is_granted('roles.update')",
      openapi: new Operation(
        tags: ['Authorization - Roles'],
        summary: 'Remove permission from role',
        description: 'Removes a permission from an existing role. Returns the updated role. Requires roles.update permission.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_OK => new Response(
            description: 'Permission removed successfully - role returned with updated permissions',
            links: new ArrayObject([
              'GetRole' => [
                'operationId' => AuthorizationOperations::ROLE_GET,
                'description' => 'Get the role details',
                'parameters' => [
                  'id' => '$response.body#/id',
                ],
              ],
              'AddPermission' => [
                'operationId' => AuthorizationOperations::ROLE_ADD_PERMISSION,
                'description' => 'Add a permission to this role',
                'parameters' => [
                  'id' => '$response.body#/id',
                ],
              ],
            ]),
          ),
          HttpResponse::HTTP_NOT_FOUND => new Response(
            description: 'Role or permission not found, or permission not assigned to role',
          ),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(
            description: 'Authentication required - missing or invalid access token',
          ),
          HttpResponse::HTTP_FORBIDDEN => new Response(
            description: 'Insufficient permissions - roles.update required',
          ),
        ],
      ),
    ),
  ],
)]
final class RoleResource
{
}
