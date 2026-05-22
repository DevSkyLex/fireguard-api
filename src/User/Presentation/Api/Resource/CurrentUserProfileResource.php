<?php

declare(strict_types=1);

namespace User\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get};
use ApiPlatform\OpenApi\Model\{Operation, Response};
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use User\Presentation\Api\Dto\Output\User\CurrentUserProfileOutput;
use User\Presentation\Api\Operation\UserOperations;
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
  ],
)]
final class CurrentUserProfileResource
{
}
