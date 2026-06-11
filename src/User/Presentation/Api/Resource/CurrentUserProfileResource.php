<?php

declare(strict_types=1);

namespace User\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get, Patch, Put};
use ApiPlatform\OpenApi\Model\{Operation, RequestBody, Response};
use ArrayObject;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use User\Presentation\Api\Dto\Input\User\CurrentUserProfileInput;
use User\Presentation\Api\Dto\Output\User\CurrentUserProfileOutput;
use User\Presentation\Api\Operation\UserOperations;
use User\Presentation\Api\Processor\User\{
  UpdateCurrentUserProfileProcessor,
  UploadCurrentUserAvatarProcessor
};
use User\Presentation\Api\Provider\User\GetCurrentUserProfileProvider;
use User\Presentation\Api\Serialization\UserSerializationGroup;

/**
 * Resource CurrentUserProfileResource.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'CurrentUserProfile',
  operations: [
    new Get(
      name: UserOperations::GET_CURRENT_PROFILE,
      uriTemplate: '/me',
      input: false,
      output: CurrentUserProfileOutput::class,
      provider: GetCurrentUserProfileProvider::class,
      normalizationContext: ['groups' => [UserSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Users'],
        summary: 'Get current user profile',
        description: 'Returns the authenticated user profile with resolved global roles and permissions.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_OK => new Response(
            description: 'Current user profile retrieved successfully',
          ),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(
            description: 'Authentication required',
          ),
          HttpResponse::HTTP_NOT_FOUND => new Response(
            description: 'Authenticated user not found',
          ),
        ],
      ),
    ),
    new Patch(
      name: UserOperations::UPDATE_CURRENT_PROFILE,
      uriTemplate: '/me',
      input: CurrentUserProfileInput::class,
      output: CurrentUserProfileOutput::class,
      processor: UpdateCurrentUserProfileProcessor::class,
      normalizationContext: ['groups' => [UserSerializationGroup::READ]],
      denormalizationContext: ['groups' => [UserSerializationGroup::WRITE]],
      security: "is_granted('profile.update')",
      openapi: new Operation(
        tags: ['Users'],
        summary: 'Update current user profile',
        description: 'Partially updates the authenticated user profile. Requires profile.update permission.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_OK => new Response(
            description: 'Current user profile updated successfully',
          ),
          HttpResponse::HTTP_BAD_REQUEST => new Response(
            description: 'Invalid request - validation failed',
          ),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(
            description: 'Authentication required',
          ),
          HttpResponse::HTTP_FORBIDDEN => new Response(
            description: 'Insufficient permissions',
          ),
          HttpResponse::HTTP_NOT_FOUND => new Response(
            description: 'Authenticated user not found',
          ),
        ],
      ),
    ),
    new Put(
      name: UserOperations::UPLOAD_CURRENT_AVATAR,
      uriTemplate: '/me/avatar',
      input: false,
      output: CurrentUserProfileOutput::class,
      processor: UploadCurrentUserAvatarProcessor::class,
      normalizationContext: ['groups' => [UserSerializationGroup::READ]],
      security: "is_granted('profile.update')",
      openapi: new Operation(
        tags: ['Users'],
        summary: 'Upload current user avatar',
        description: 'Replaces the authenticated user avatar. Accepted formats: JPEG, PNG, WebP, GIF (max 5 MB). Requires profile.update permission.',
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
            description: 'Current user avatar uploaded successfully',
          ),
          HttpResponse::HTTP_UNPROCESSABLE_ENTITY => new Response(
            description: 'Invalid file - missing, too large, or unsupported MIME type',
          ),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(
            description: 'Authentication required',
          ),
          HttpResponse::HTTP_FORBIDDEN => new Response(
            description: 'Insufficient permissions',
          ),
          HttpResponse::HTTP_NOT_FOUND => new Response(
            description: 'Authenticated user not found',
          ),
        ],
      ),
    ),
  ],
)]
final class CurrentUserProfileResource
{
}
