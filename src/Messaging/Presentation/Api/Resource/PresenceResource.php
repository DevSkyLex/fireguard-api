<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get, GetCollection, Post};
use ApiPlatform\OpenApi\Model\{Operation, Parameter};
use Messaging\Presentation\Api\Dto\Input\PingPresenceInput;
use Messaging\Presentation\Api\Dto\Output\{PingPresenceOutput, PresenceOutput};
use Messaging\Presentation\Api\Dto\Output\PresenceSubscriptionOutput;
use Messaging\Presentation\Api\Operation\PresenceOperations;
use Messaging\Presentation\Api\Processor\Presence\PingPresenceProcessor;
use Messaging\Presentation\Api\Provider\Presence\{GetPresenceProvider, GetPresenceSubscriptionProvider};
use Symfony\Component\HttpFoundation\Response;

/**
 * Resource PresenceResource.
 *
 * Online presence (L2.7) — **no database table**. Backed entirely by
 * `Shared\Application\Port\Outbound\CachePort` (Redis in production, the
 * filesystem adapter in dev/test — see `MODULE.md`'s per-process caveat)
 * with a 90 second TTL per member. The client is expected to call
 * `POST /presence/ping` roughly every 60 seconds while active, and to
 * read presence for the specific member ids it is currently displaying via
 * `GET /presence` — there is deliberately NO "list all online members"
 * endpoint (see `MODULE.md`).
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'Presence',
  operations: [
    new Get(
      name: PresenceOperations::SUBSCRIPTION,
      uriTemplate: '/presence/subscription',
      output: PresenceSubscriptionOutput::class,
      provider: GetPresenceSubscriptionProvider::class,
      security: self::SECURITY_ROLE_USER,
      normalizationContext: ['groups' => ['presence:read']],
      parameters: ['organization' => new \ApiPlatform\Metadata\QueryParameter(schema: ['type' => 'string'], required: true, castToArray: false)],
    ),
    new Post(
      name: PresenceOperations::PING,
      uriTemplate: '/presence/ping',
      input: PingPresenceInput::class,
      output: PingPresenceOutput::class,
      processor: PingPresenceProcessor::class,
      status: Response::HTTP_OK,
      security: self::SECURITY_ROLE_USER,
    ),
    new GetCollection(
      name: PresenceOperations::GET,
      uriTemplate: '/presence',
      output: PresenceOutput::class,
      provider: GetPresenceProvider::class,
      paginationEnabled: false,
      security: self::SECURITY_ROLE_USER,
      parameters: [
        'organization' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string'],
          description: 'Organization IRI.',
          required: true,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(name: 'organization', in: 'query', description: 'Organization IRI.', required: true, schema: ['type' => 'string']),
        ),
        'memberIds' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string'],
          description: 'Comma-separated organization member ids to check presence for (max 100). Required — there is no "list all online members" mode.',
          required: true,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(name: 'memberIds', in: 'query', description: 'Comma-separated organization member ids to check presence for (max 100). Required — there is no "list all online members" mode.', required: true, schema: ['type' => 'string']),
        ),
      ],
      openapi: new Operation(parameters: []),
    ),
  ],
)]
final class PresenceResource
{
  private const string SECURITY_ROLE_USER = "is_granted('ROLE_USER')";
}
