<?php

declare(strict_types=1);

namespace Authorization\Presentation\Api\Resource;

use ApiPlatform\Metadata\{
  ApiResource,
  Get,
  GetCollection,
  Post,
  Patch,
  Delete
};
use ApiPlatform\OpenApi\Model\{
  Operation,
  Response
};
use ArrayObject;
use Authorization\Presentation\Api\Dto\PermissionInput;
use Authorization\Presentation\Api\Dto\PermissionOutput;
use Authorization\Presentation\Api\Processor\{
  CreatePermissionProcessor,
  UpdatePermissionProcessor,
  DeletePermissionProcessor
};
use Authorization\Presentation\Api\Provider\{
  GetPermissionProvider,
  ListPermissionsProvider
};
use Authorization\Presentation\Api\Serialization\PermissionSerializationGroup;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Resource PermissionResource
 * @final
 *
 * Permission Management API Resource.
 *
 * This resource exposes endpoints for managing permissions in the RBAC system.
 * Permissions define specific access rights using a "resource.action" naming
 * convention. Permissions can be assigned to roles, which are then assigned
 * to users.
 *
 * @category Resource
 * @package Authorization\Presentation\Api\Resource
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'Permission',
  description: 'Permission management. Permissions define specific access rights.',
  operations: [
    new GetCollection(
      name: 'permission_list',
      uriTemplate: '/permissions',
      input: false,
      output: PermissionOutput::class,
      provider: ListPermissionsProvider::class,
      normalizationContext: ['groups' => [PermissionSerializationGroup::READ]],
      security: "is_granted('ROLE_ADMIN')",
      openapi: new Operation(
        tags: ['Authorization - Permissions'],
        summary: 'List all permissions',
        description: 'Returns a list of all available permissions in the system. Permissions follow the "resource.action" naming convention (e.g., users.create, posts.delete). Requires ROLE_ADMIN.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_OK => new Response(
            description: 'List of permissions retrieved successfully',
          ),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(
            description: 'Authentication required - missing or invalid access token'
          ),
          HttpResponse::HTTP_FORBIDDEN => new Response(
            description: 'Insufficient permissions - ROLE_ADMIN required'
          ),
        ],
      ),
    ),
    new Get(
      name: 'permission_get',
      uriTemplate: '/permissions/{id}',
      input: false,
      output: PermissionOutput::class,
      provider: GetPermissionProvider::class,
      normalizationContext: ['groups' => [PermissionSerializationGroup::READ]],
      security: "is_granted('ROLE_ADMIN')",
      openapi: new Operation(
        tags: ['Authorization - Permissions'],
        summary: 'Get permission details',
        description: 'Returns details of a specific permission including its name, description, and creation date. Requires ROLE_ADMIN.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_OK => new Response(
            description: 'Permission details retrieved successfully',
            links: new ArrayObject([
              'UpdatePermission' => [
                'operationId' => 'permission_update',
                'description' => 'Update this permission',
                'parameters' => [
                  'id' => '$response.body#/id',
                ],
              ],
              'DeletePermission' => [
                'operationId' => 'permission_delete',
                'description' => 'Delete this permission',
                'parameters' => [
                  'id' => '$response.body#/id',
                ],
              ],
            ]),
          ),
          HttpResponse::HTTP_NOT_FOUND => new Response(
            description: 'Permission not found'
          ),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(
            description: 'Authentication required - missing or invalid access token'
          ),
          HttpResponse::HTTP_FORBIDDEN => new Response(
            description: 'Insufficient permissions - ROLE_ADMIN required'
          ),
        ],
      ),
    ),
    new Post(
      name: 'permission_create',
      uriTemplate: '/permissions',
      input: PermissionInput::class,
      output: PermissionOutput::class,
      processor: CreatePermissionProcessor::class,
      normalizationContext: ['groups' => [PermissionSerializationGroup::READ]],
      denormalizationContext: ['groups' => [PermissionSerializationGroup::WRITE]],
      security: "is_granted('ROLE_SUPER_ADMIN')",
      openapi: new Operation(
        tags: ['Authorization - Permissions'],
        summary: 'Create a new permission',
        description: 'Creates a new permission with a unique name following the "resource.action" format. Permission names must be unique across the system. Requires ROLE_SUPER_ADMIN.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_CREATED => new Response(
            description: 'Permission created successfully',
            links: new ArrayObject([
              'GetPermission' => [
                'operationId' => 'permission_get',
                'description' => 'Get the created permission details',
                'parameters' => [
                  'id' => '$response.body#/id',
                ],
              ],
              'AssignToRole' => [
                'operationId' => 'role_add_permission',
                'description' => 'Assign this permission to a role',
              ],
            ]),
          ),
          HttpResponse::HTTP_BAD_REQUEST => new Response(
            description: 'Invalid request - validation failed (name format or length)'
          ),
          HttpResponse::HTTP_CONFLICT => new Response(
            description: 'Permission name already exists'
          ),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(
            description: 'Authentication required - missing or invalid access token'
          ),
          HttpResponse::HTTP_FORBIDDEN => new Response(
            description: 'Insufficient permissions - ROLE_SUPER_ADMIN required'
          ),
        ],
      ),
    ),
    new Patch(
      name: 'permission_update',
      uriTemplate: '/permissions/{id}',
      input: PermissionInput::class,
      output: PermissionOutput::class,
      processor: UpdatePermissionProcessor::class,
      normalizationContext: ['groups' => [PermissionSerializationGroup::READ]],
      denormalizationContext: ['groups' => [PermissionSerializationGroup::UPDATE]],
      security: "is_granted('ROLE_SUPER_ADMIN')",
      openapi: new Operation(
        tags: ['Authorization - Permissions'],
        summary: 'Update a permission',
        description: 'Updates the description of an existing permission. The permission name cannot be changed after creation to maintain integrity. Requires ROLE_SUPER_ADMIN.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_OK => new Response(
            description: 'Permission updated successfully',
            links: new ArrayObject([
              'GetPermission' => [
                'operationId' => 'permission_get',
                'description' => 'Get the updated permission details',
                'parameters' => [
                  'id' => '$response.body#/id',
                ],
              ],
            ]),
          ),
          HttpResponse::HTTP_BAD_REQUEST => new Response(
            description: 'Invalid request - validation failed'
          ),
          HttpResponse::HTTP_NOT_FOUND => new Response(
            description: 'Permission not found'
          ),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(
            description: 'Authentication required - missing or invalid access token'
          ),
          HttpResponse::HTTP_FORBIDDEN => new Response(
            description: 'Insufficient permissions - ROLE_SUPER_ADMIN required'
          ),
        ],
      ),
    ),
    new Delete(
      name: 'permission_delete',
      uriTemplate: '/permissions/{id}',
      input: false,
      output: false,
      processor: DeletePermissionProcessor::class,
      security: "is_granted('ROLE_SUPER_ADMIN')",
      openapi: new Operation(
        tags: ['Authorization - Permissions'],
        summary: 'Delete a permission',
        description: 'Permanently deletes a permission. This will automatically remove the permission from all roles that have it assigned. Use with caution. Requires ROLE_SUPER_ADMIN.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_NO_CONTENT => new Response(
            description: 'Permission deleted successfully',
          ),
          HttpResponse::HTTP_NOT_FOUND => new Response(
            description: 'Permission not found'
          ),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(
            description: 'Authentication required - missing or invalid access token'
          ),
          HttpResponse::HTTP_FORBIDDEN => new Response(
            description: 'Insufficient permissions - ROLE_SUPER_ADMIN required'
          ),
        ],
      ),
    )
  ]
)]
final class PermissionResource
{
}
