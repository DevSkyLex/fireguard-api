<?php

declare(strict_types=1);

namespace Authorization\Presentation\Api\Resource;

use ApiPlatform\Metadata\{
  ApiResource,
  Delete,
  Get,
  GetCollection,
  Patch,
  Post
};
use ApiPlatform\OpenApi\Model\{
  Operation,
  Response
};
use ArrayObject;
use Authorization\Presentation\Api\Dto\Input\Permission\PermissionInput;
use Authorization\Presentation\Api\Dto\Output\Permission\PermissionOutput;
use Authorization\Presentation\Api\Operation\AuthorizationOperations;
use Authorization\Presentation\Api\Processor\Permission\{CreatePermissionProcessor, DeletePermissionProcessor, UpdatePermissionProcessor};
use Authorization\Presentation\Api\Provider\Permission\{GetPermissionProvider, ListPermissionsProvider};
use Authorization\Presentation\Api\Serialization\PermissionSerializationGroup;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Resource PermissionResource.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'Permission',
  description: 'Permission management. Permissions define specific access rights.',
  operations: [
    new GetCollection(
      name: AuthorizationOperations::PERMISSION_LIST,
      uriTemplate: '/permissions',
      input: false,
      output: PermissionOutput::class,
      provider: ListPermissionsProvider::class,
      normalizationContext: ['groups' => [PermissionSerializationGroup::READ]],
      security: "is_granted('permissions.read')",
      openapi: new Operation(
        tags: [self::TAG],
        summary: 'List all permissions',
        description: 'Returns a list of all available permissions in the system. Permissions follow the "resource.action" naming convention (e.g., users.create, posts.delete). Requires permissions.read permission.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_OK => new Response(
            description: 'List of permissions retrieved successfully',
          ),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(
            description: self::AUTHENTICATION_REQUIRED,
          ),
          HttpResponse::HTTP_FORBIDDEN => new Response(
            description: 'Insufficient permissions - permissions.read required',
          ),
        ],
      ),
    ),
    new Get(
      name: AuthorizationOperations::PERMISSION_GET,
      uriTemplate: self::ITEM_URI,
      input: false,
      output: PermissionOutput::class,
      provider: GetPermissionProvider::class,
      normalizationContext: ['groups' => [PermissionSerializationGroup::READ]],
      security: "is_granted('permissions.read')",
      openapi: new Operation(
        tags: [self::TAG],
        summary: 'Get permission details',
        description: 'Returns details of a specific permission including its name, description, and creation date. Requires permissions.read permission.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_OK => new Response(
            description: 'Permission details retrieved successfully',
            links: new ArrayObject([
              'UpdatePermission' => [
                'operationId' => AuthorizationOperations::PERMISSION_UPDATE,
                'description' => 'Update this permission',
                'parameters' => [
                  'id' => self::RESPONSE_ID,
                ],
              ],
              'DeletePermission' => [
                'operationId' => AuthorizationOperations::PERMISSION_DELETE,
                'description' => 'Delete this permission',
                'parameters' => [
                  'id' => self::RESPONSE_ID,
                ],
              ],
            ]),
          ),
          HttpResponse::HTTP_NOT_FOUND => new Response(
            description: self::NOT_FOUND,
          ),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(
            description: self::AUTHENTICATION_REQUIRED,
          ),
          HttpResponse::HTTP_FORBIDDEN => new Response(
            description: 'Insufficient permissions - permissions.read required',
          ),
        ],
      ),
    ),
    new Post(
      name: AuthorizationOperations::PERMISSION_CREATE,
      uriTemplate: '/permissions',
      input: PermissionInput::class,
      output: PermissionOutput::class,
      processor: CreatePermissionProcessor::class,
      normalizationContext: ['groups' => [PermissionSerializationGroup::READ]],
      denormalizationContext: ['groups' => [PermissionSerializationGroup::WRITE]],
      security: self::MANAGE_SECURITY,
      openapi: new Operation(
        tags: [self::TAG],
        summary: 'Create a new permission',
        description: 'Creates a new permission with a unique name following the "resource.action" format. Permission names must be unique across the system. Requires permissions.manage permission.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_CREATED => new Response(
            description: 'Permission created successfully',
            links: new ArrayObject([
              'GetPermission' => [
                'operationId' => AuthorizationOperations::PERMISSION_GET,
                'description' => 'Get the created permission details',
                'parameters' => [
                  'id' => self::RESPONSE_ID,
                ],
              ],
              'AssignToRole' => [
                'operationId' => 'role_add_permission',
                'description' => 'Assign this permission to a role',
              ],
            ]),
          ),
          HttpResponse::HTTP_BAD_REQUEST => new Response(
            description: 'Invalid request - validation failed (name format or length)',
          ),
          HttpResponse::HTTP_CONFLICT => new Response(
            description: 'Permission name already exists',
          ),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(
            description: self::AUTHENTICATION_REQUIRED,
          ),
          HttpResponse::HTTP_FORBIDDEN => new Response(
            description: self::MANAGE_FORBIDDEN,
          ),
        ],
      ),
    ),
    new Patch(
      name: AuthorizationOperations::PERMISSION_UPDATE,
      uriTemplate: self::ITEM_URI,
      input: PermissionInput::class,
      output: PermissionOutput::class,
      processor: UpdatePermissionProcessor::class,
      normalizationContext: ['groups' => [PermissionSerializationGroup::READ]],
      denormalizationContext: ['groups' => [PermissionSerializationGroup::UPDATE]],
      security: self::MANAGE_SECURITY,
      openapi: new Operation(
        tags: [self::TAG],
        summary: 'Update a permission',
        description: 'Updates the description of an existing permission. The permission name cannot be changed after creation to maintain integrity. Requires permissions.manage permission.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_OK => new Response(
            description: 'Permission updated successfully',
            links: new ArrayObject([
              'GetPermission' => [
                'operationId' => AuthorizationOperations::PERMISSION_GET,
                'description' => 'Get the updated permission details',
                'parameters' => [
                  'id' => self::RESPONSE_ID,
                ],
              ],
            ]),
          ),
          HttpResponse::HTTP_BAD_REQUEST => new Response(
            description: 'Invalid request - validation failed',
          ),
          HttpResponse::HTTP_NOT_FOUND => new Response(
            description: self::NOT_FOUND,
          ),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(
            description: self::AUTHENTICATION_REQUIRED,
          ),
          HttpResponse::HTTP_FORBIDDEN => new Response(
            description: self::MANAGE_FORBIDDEN,
          ),
        ],
      ),
    ),
    new Delete(
      name: AuthorizationOperations::PERMISSION_DELETE,
      uriTemplate: self::ITEM_URI,
      input: false,
      output: false,
      processor: DeletePermissionProcessor::class,
      security: self::MANAGE_SECURITY,
      openapi: new Operation(
        tags: [self::TAG],
        summary: 'Delete a permission',
        description: 'Permanently deletes a permission. This will automatically remove the permission from all roles that have it assigned. Use with caution. Requires permissions.manage permission.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_NO_CONTENT => new Response(
            description: 'Permission deleted successfully',
          ),
          HttpResponse::HTTP_NOT_FOUND => new Response(
            description: self::NOT_FOUND,
          ),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(
            description: self::AUTHENTICATION_REQUIRED,
          ),
          HttpResponse::HTTP_FORBIDDEN => new Response(
            description: self::MANAGE_FORBIDDEN,
          ),
        ],
      ),
    ),
  ],
)]
final class PermissionResource
{
  private const TAG = 'Authorization - Permissions';

  private const AUTHENTICATION_REQUIRED = 'Authentication required - missing or invalid access token';

  private const ITEM_URI = '/permissions/{id}';

  private const RESPONSE_ID = '$response.body#/id';

  private const NOT_FOUND = 'Permission not found';

  private const MANAGE_SECURITY = "is_granted('permissions.manage')";

  private const MANAGE_FORBIDDEN = 'Insufficient permissions - permissions.manage required';
}
