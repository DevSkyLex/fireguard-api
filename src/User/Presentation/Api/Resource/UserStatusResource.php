<?php

declare(strict_types=1);

namespace User\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, GetCollection};
use ApiPlatform\OpenApi\Model\{Operation, Response};
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use User\Presentation\Api\Dto\Output\User\UserStatusOptionOutput;
use User\Presentation\Api\Operation\UserOperations;
use User\Presentation\Api\Provider\User\ListUserStatusesProvider;
use User\Presentation\Api\Serialization\UserSerializationGroup;

#[ApiResource(
  shortName: 'UserStatus',
  routePrefix: '/users',
  description: 'Reference data for user statuses.',
  operations: [
    new GetCollection(
      name: UserOperations::LIST_STATUSES,
      uriTemplate: '/statuses',
      input: false,
      output: UserStatusOptionOutput::class,
      provider: ListUserStatusesProvider::class,
      normalizationContext: ['groups' => [UserSerializationGroup::READ]],
      security: "is_granted('users.read')",
      openapi: new Operation(
        tags: ['Users'],
        summary: 'List user statuses',
        description: 'Returns supported user statuses for filters and select inputs. Requires users.read permission.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'User statuses retrieved successfully'),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(description: 'Authentication required'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
        ],
      ),
    ),
  ],
)]
final class UserStatusResource
{
}
