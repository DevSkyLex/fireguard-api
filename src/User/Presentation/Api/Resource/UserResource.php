<?php

declare(strict_types=1);

namespace User\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Delete, Get, GetCollection, Patch, Post, Put};
use ApiPlatform\OpenApi\Model\{Operation, RequestBody, Response};
use ArrayObject;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use User\Presentation\Api\Dto\Input\User\UserInput;
use User\Presentation\Api\Dto\Output\User\UserOutput;
use User\Presentation\Api\Operation\UserOperations;
use User\Presentation\Api\Processor\User\{
  ActivateUserProcessor,
  CreateUserProcessor,
  DeactivateUserProcessor,
  DeleteUserProcessor,
  UpdateUserProcessor,
  UploadUserAvatarProcessor,
  VerifyUserEmailProcessor
};
use User\Presentation\Api\Provider\User\{ListUsersProvider, UserProvider};
use User\Presentation\Api\Serialization\UserSerializationGroup;

/**
 * Resource UserResource.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'User',
  operations: [
    new Post(
      name: UserOperations::CREATE,
      uriTemplate: '/users',
      input: UserInput::class,
      output: UserOutput::class,
      processor: CreateUserProcessor::class,
      normalizationContext: ['groups' => [UserSerializationGroup::READ]],
      denormalizationContext: ['groups' => [UserSerializationGroup::WRITE]],
      security: "is_granted('users.create')",
      openapi: new Operation(
        tags: ['Users'],
        summary: 'Create user',
        description: 'Creates a new user account. Requires users.create permission.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_CREATED => new Response(
            description: 'User created successfully',
          ),
          HttpResponse::HTTP_BAD_REQUEST => new Response(
            description: 'Invalid request - validation failed',
          ),
          HttpResponse::HTTP_CONFLICT => new Response(
            description: 'User already exists',
          ),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(
            description: 'Authentication required',
          ),
          HttpResponse::HTTP_FORBIDDEN => new Response(
            description: 'Insufficient permissions',
          ),
        ],
      ),
    ),
    new Get(
      name: UserOperations::GET,
      uriTemplate: '/users/{id}',
      input: false,
      output: UserOutput::class,
      provider: UserProvider::class,
      normalizationContext: ['groups' => [UserSerializationGroup::READ]],
      security: "is_granted('users.read')",
      openapi: new Operation(
        tags: ['Users'],
        summary: 'Get user',
        description: 'Retrieve a user by ID. Requires users.read permission.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_OK => new Response(
            description: 'User retrieved successfully',
          ),
          HttpResponse::HTTP_NOT_FOUND => new Response(
            description: 'User not found',
          ),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(
            description: 'Authentication required',
          ),
          HttpResponse::HTTP_FORBIDDEN => new Response(
            description: 'Insufficient permissions',
          ),
        ],
      ),
    ),
    new GetCollection(
      name: UserOperations::LIST,
      uriTemplate: '/users',
      input: false,
      output: UserOutput::class,
      provider: ListUsersProvider::class,
      paginationEnabled: true,
      paginationClientItemsPerPage: true,
      paginationItemsPerPage: 30,
      normalizationContext: ['groups' => [UserSerializationGroup::READ]],
      security: "is_granted('users.read')",
      openapi: new Operation(
        tags: ['Users'],
        summary: 'List users',
        description: 'Returns a paginated list of users. Requires users.read permission.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_OK => new Response(
            description: 'List of users retrieved successfully',
          ),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(
            description: 'Authentication required',
          ),
          HttpResponse::HTTP_FORBIDDEN => new Response(
            description: 'Insufficient permissions',
          ),
        ],
      ),
    ),
    new Patch(
      name: UserOperations::UPDATE,
      uriTemplate: '/users/{id}',
      input: UserInput::class,
      output: UserOutput::class,
      processor: UpdateUserProcessor::class,
      normalizationContext: ['groups' => [UserSerializationGroup::READ]],
      denormalizationContext: ['groups' => [UserSerializationGroup::WRITE]],
      security: "is_granted('users.update')",
      openapi: new Operation(
        tags: ['Users'],
        summary: 'Update user (partial)',
        description: 'Partially update a user by ID. Requires users.update permission.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_OK => new Response(
            description: 'User updated successfully',
          ),
          HttpResponse::HTTP_BAD_REQUEST => new Response(
            description: 'Invalid request - validation failed',
          ),
          HttpResponse::HTTP_NOT_FOUND => new Response(
            description: 'User not found',
          ),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(
            description: 'Authentication required',
          ),
          HttpResponse::HTTP_FORBIDDEN => new Response(
            description: 'Insufficient permissions',
          ),
        ],
      ),
    ),
    new Put(
      name: UserOperations::REPLACE,
      uriTemplate: '/users/{id}',
      input: UserInput::class,
      output: UserOutput::class,
      processor: UpdateUserProcessor::class,
      normalizationContext: ['groups' => [UserSerializationGroup::READ]],
      denormalizationContext: ['groups' => [UserSerializationGroup::WRITE]],
      security: "is_granted('users.update')",
      openapi: new Operation(
        tags: ['Users'],
        summary: 'Replace user',
        description: 'Replace a user by ID. Requires users.update permission.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_OK => new Response(
            description: 'User replaced successfully',
          ),
          HttpResponse::HTTP_BAD_REQUEST => new Response(
            description: 'Invalid request - validation failed',
          ),
          HttpResponse::HTTP_NOT_FOUND => new Response(
            description: 'User not found',
          ),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(
            description: 'Authentication required',
          ),
          HttpResponse::HTTP_FORBIDDEN => new Response(
            description: 'Insufficient permissions',
          ),
        ],
      ),
    ),
    new Delete(
      name: UserOperations::DELETE,
      uriTemplate: '/users/{id}',
      input: false,
      output: false,
      processor: DeleteUserProcessor::class,
      security: "is_granted('users.delete')",
      openapi: new Operation(
        tags: ['Users'],
        summary: 'Delete user',
        description: 'Delete a user by ID. Requires users.delete permission.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_NO_CONTENT => new Response(
            description: 'User deleted successfully',
          ),
          HttpResponse::HTTP_NOT_FOUND => new Response(
            description: 'User not found',
          ),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(
            description: 'Authentication required',
          ),
          HttpResponse::HTTP_FORBIDDEN => new Response(
            description: 'Insufficient permissions',
          ),
        ],
      ),
    ),
    new Post(
      name: UserOperations::ACTIVATE,
      uriTemplate: '/users/{id}/activate',
      input: false,
      output: UserOutput::class,
      processor: ActivateUserProcessor::class,
      normalizationContext: ['groups' => [UserSerializationGroup::READ]],
      security: "is_granted('users.update')",
      openapi: new Operation(
        tags: ['Users'],
        summary: 'Activate user',
        description: 'Activate a user account. Requires users.update permission.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_OK => new Response(
            description: 'User activated successfully',
          ),
          HttpResponse::HTTP_NOT_FOUND => new Response(
            description: 'User not found',
          ),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(
            description: 'Authentication required',
          ),
          HttpResponse::HTTP_FORBIDDEN => new Response(
            description: 'Insufficient permissions',
          ),
        ],
      ),
    ),
    new Post(
      name: UserOperations::DEACTIVATE,
      uriTemplate: '/users/{id}/deactivate',
      input: false,
      output: UserOutput::class,
      processor: DeactivateUserProcessor::class,
      normalizationContext: ['groups' => [UserSerializationGroup::READ]],
      security: "is_granted('users.update')",
      openapi: new Operation(
        tags: ['Users'],
        summary: 'Deactivate user',
        description: 'Deactivate a user account. Requires users.update permission.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_OK => new Response(
            description: 'User deactivated successfully',
          ),
          HttpResponse::HTTP_NOT_FOUND => new Response(
            description: 'User not found',
          ),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(
            description: 'Authentication required',
          ),
          HttpResponse::HTTP_FORBIDDEN => new Response(
            description: 'Insufficient permissions',
          ),
        ],
      ),
    ),
    new Post(
      name: UserOperations::VERIFY_EMAIL,
      uriTemplate: '/users/{id}/verify-email',
      input: false,
      output: UserOutput::class,
      processor: VerifyUserEmailProcessor::class,
      normalizationContext: ['groups' => [UserSerializationGroup::READ]],
      security: "is_granted('users.update')",
      openapi: new Operation(
        tags: ['Users'],
        summary: 'Verify user email',
        description: 'Mark a user email as verified. Requires users.update permission.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_OK => new Response(
            description: 'User email verified successfully',
          ),
          HttpResponse::HTTP_NOT_FOUND => new Response(
            description: 'User not found',
          ),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(
            description: 'Authentication required',
          ),
          HttpResponse::HTTP_FORBIDDEN => new Response(
            description: 'Insufficient permissions',
          ),
        ],
      ),
    ),
    new Post(
      name: UserOperations::UPLOAD_AVATAR,
      uriTemplate: '/users/{id}/avatar',
      input: false,
      output: UserOutput::class,
      processor: UploadUserAvatarProcessor::class,
      normalizationContext: ['groups' => [UserSerializationGroup::READ]],
      security: "is_granted('users.update')",
      openapi: new Operation(
        tags: ['Users'],
        summary: 'Upload user avatar',
        description: 'Uploads an avatar image for a user. Accepted formats: JPEG, PNG, WebP, GIF (max 5 MB). The image is resized to four WebP variants (256, 128, 64, 32 px) with cover crop. Requires users.update permission.',
        security: [['bearerAuth' => []]],
        requestBody: new RequestBody(
          description: 'Avatar image file (JPEG, PNG, WebP or GIF, max 5 MB)',
          content: new ArrayObject([
            'multipart/form-data' => [
              'schema' => [
                'type' => 'object',
                'required' => ['avatar'],
                'properties' => [
                  'avatar' => [
                    'type' => 'string',
                    'format' => 'binary',
                    'description' => 'Image file (JPEG, PNG, WebP or GIF), max 5 MB',
                  ],
                ],
              ],
            ],
          ]),
          required: true,
        ),
        responses: [
          HttpResponse::HTTP_OK => new Response(
            description: 'Avatar uploaded — user output with updated avatarUrl returned',
          ),
          HttpResponse::HTTP_UNPROCESSABLE_ENTITY => new Response(
            description: 'Invalid file — missing, too large, or unsupported MIME type',
          ),
          HttpResponse::HTTP_NOT_FOUND => new Response(
            description: 'User not found',
          ),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(
            description: 'Authentication required',
          ),
          HttpResponse::HTTP_FORBIDDEN => new Response(
            description: 'Insufficient permissions',
          ),
        ],
      ),
    ),
  ],
)]
final class UserResource
{
}
