<?php

declare(strict_types=1);

namespace Session\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Delete, Get, GetCollection, Post};
use ApiPlatform\OpenApi\Model\{Operation, Response};
use Session\Presentation\Api\Dto\Output\Session\{RevokeOtherSessionsOutput, SessionOutput};
use Session\Presentation\Api\Operation\SessionOperations;
use Session\Presentation\Api\Processor\Session\{RevokeAllSessionsProcessor, RevokeOtherSessionsProcessor, RevokeSessionProcessor};
use Session\Presentation\Api\Provider\Session\{GetSessionProvider, ListUserSessionsProvider};
use Session\Presentation\Api\Serialization\SessionSerializationGroup;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Resource SessionResource.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'Session',
  description: 'User session management. Sessions track user authentication state across devices.',
  operations: [
    new GetCollection(
      name: SessionOperations::LIST,
      description: 'Returns all active sessions for the authenticated user.',
      uriTemplate: '/sessions',
      input: false,
      output: SessionOutput::class,
      provider: ListUserSessionsProvider::class,
      paginationEnabled: true,
      paginationClientItemsPerPage: true,
      paginationMaximumItemsPerPage: 100,
      paginationItemsPerPage: 30,
      normalizationContext: ['groups' => [SessionSerializationGroup::READ]],
      security: "is_granted('sessions.read')",
      openapi: new Operation(
        tags: ['Sessions'],
        summary: 'List sessions',
        description: 'Returns all active sessions for the authenticated user.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_OK => new Response(
            description: 'List of sessions retrieved successfully',
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
      name: SessionOperations::GET,
      description: 'Get details of a specific session.',
      uriTemplate: '/sessions/{id}',
      input: false,
      output: SessionOutput::class,
      provider: GetSessionProvider::class,
      normalizationContext: ['groups' => [SessionSerializationGroup::READ]],
      security: "is_granted('sessions.read')",
      openapi: new Operation(
        tags: ['Sessions'],
        summary: 'Get session',
        description: 'Get details of a specific session.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_OK => new Response(
            description: 'Session retrieved successfully',
          ),
          HttpResponse::HTTP_NOT_FOUND => new Response(
            description: 'Session not found',
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
      name: SessionOperations::REVOKE,
      description: 'Revoke a specific session. The user will be logged out from that device.',
      uriTemplate: '/sessions/{id}',
      input: false,
      output: false,
      processor: RevokeSessionProcessor::class,
      security: "is_granted('sessions.revoke')",
      openapi: new Operation(
        tags: ['Sessions'],
        summary: 'Revoke session',
        description: 'Revoke a specific session. The user will be logged out from that device.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_NO_CONTENT => new Response(
            description: 'Session revoked successfully',
          ),
          HttpResponse::HTTP_NOT_FOUND => new Response(
            description: 'Session not found',
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
      name: SessionOperations::REVOKE_ALL,
      description: 'Revoke all sessions for the authenticated user (logout from all devices).',
      uriTemplate: '/sessions/revoke-all',
      input: false,
      output: false,
      processor: RevokeAllSessionsProcessor::class,
      security: "is_granted('sessions.revoke')",
      openapi: new Operation(
        tags: ['Sessions'],
        summary: 'Revoke all sessions',
        description: 'Revoke all sessions for the authenticated user (logout from all devices).',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_NO_CONTENT => new Response(
            description: 'All sessions revoked successfully',
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
      name: SessionOperations::REVOKE_OTHERS,
      description: 'Revoke every other session of the authenticated user, keeping the current one active.',
      uriTemplate: '/sessions/revoke-others',
      input: false,
      output: RevokeOtherSessionsOutput::class,
      processor: RevokeOtherSessionsProcessor::class,
      status: HttpResponse::HTTP_OK,
      normalizationContext: ['groups' => [SessionSerializationGroup::REVOKE_OTHERS]],
      security: "is_granted('sessions.revoke')",
      openapi: new Operation(
        tags: ['Sessions'],
        summary: 'Revoke other sessions',
        description: 'Revokes every active session of the authenticated user except the one backing the current request, signing the user out on every other device while staying signed in here. Idempotent: calling it again once there is nothing else to revoke returns a zero count.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_OK => new Response(
            description: 'Other sessions revoked successfully',
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
final class SessionResource
{
}
