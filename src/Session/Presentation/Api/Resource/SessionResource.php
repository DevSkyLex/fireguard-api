<?php

declare(strict_types=1);

namespace Session\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Delete, Get, GetCollection, Post};
use Session\Presentation\Api\Dto\SessionOutput;
use Session\Presentation\Api\Processor\RevokeAllSessionsProcessor;
use Session\Presentation\Api\Processor\RevokeSessionProcessor;
use Session\Presentation\Api\Provider\GetSessionProvider;
use Session\Presentation\Api\Provider\ListUserSessionsProvider;

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
      name: 'session_list',
      description: 'Returns all active sessions for the authenticated user.',
      uriTemplate: '/sessions',
      input: false,
      output: SessionOutput::class,
      provider: ListUserSessionsProvider::class
    ),
    new Get(
      name: 'session_get',
      description: 'Get details of a specific session.',
      uriTemplate: '/sessions/{id}',
      input: false,
      output: SessionOutput::class,
      provider: GetSessionProvider::class
    ),
    new Delete(
      name: 'session_revoke',
      description: 'Revoke a specific session. The user will be logged out from that device.',
      uriTemplate: '/sessions/{id}',
      input: false,
      output: false,
      processor: RevokeSessionProcessor::class
    ),
    new Post(
      name: 'session_revoke_all',
      description: 'Revoke all sessions for the authenticated user (logout from all devices).',
      uriTemplate: '/sessions/revoke-all',
      input: false,
      output: SessionOutput::class,
      processor: RevokeAllSessionsProcessor::class
    ),
  ]
)]
final class SessionResource
{
}
