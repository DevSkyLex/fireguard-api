<?php

declare(strict_types=1);

namespace Session\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Delete, Get, GetCollection, Post};
use Session\Presentation\Api\Dto\Output\Session\SessionOutput;
use Session\Presentation\Api\Operation\SessionOperations;
use Session\Presentation\Api\Processor\Session\{RevokeAllSessionsProcessor, RevokeSessionProcessor};
use Session\Presentation\Api\Provider\Session\{GetSessionProvider, ListUserSessionsProvider};
use Session\Presentation\Api\Serialization\SessionSerializationGroup;

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
      normalizationContext: ['groups' => [SessionSerializationGroup::READ]],
    ),
    new Get(
      name: SessionOperations::GET,
      description: 'Get details of a specific session.',
      uriTemplate: '/sessions/{id}',
      input: false,
      output: SessionOutput::class,
      provider: GetSessionProvider::class,
      normalizationContext: ['groups' => [SessionSerializationGroup::READ]],
    ),
    new Delete(
      name: SessionOperations::REVOKE,
      description: 'Revoke a specific session. The user will be logged out from that device.',
      uriTemplate: '/sessions/{id}',
      input: false,
      output: false,
      processor: RevokeSessionProcessor::class,
    ),
    new Post(
      name: SessionOperations::REVOKE_ALL,
      description: 'Revoke all sessions for the authenticated user (logout from all devices).',
      uriTemplate: '/sessions/revoke-all',
      input: false,
      output: false,
      processor: RevokeAllSessionsProcessor::class,
    ),
  ],
)]
final class SessionResource
{
}
