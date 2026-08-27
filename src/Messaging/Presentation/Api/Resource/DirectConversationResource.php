<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, GetCollection, Post};
use ApiPlatform\OpenApi\Model\{Operation, Parameter};
use Messaging\Presentation\Api\Dto\Input\GetOrCreateDirectConversationInput;
use Messaging\Presentation\Api\Dto\Output\ConversationOutput;
use Messaging\Presentation\Api\Processor\Conversation\GetOrCreateDirectConversationProcessor;
use Messaging\Presentation\Api\Provider\Conversation\ListDirectConversationsProvider;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resource DirectConversationResource.
 *
 * 1-to-1 private conversations between two organization members (L2.4) — a
 * `Conversation` with `subjectType=DIRECT`, `visibility=PARTICIPANTS`, and
 * `subjectId` set to the deterministic, order-independent pair key derived
 * by `Messaging\Domain\Service\DirectConversationKey`. Mirrors
 * `ChannelResource`: a dedicated top-level creation endpoint PLUS its own
 * "list mine" collection, while messages/read/subscription/favorite reuse
 * the EXISTING `/api/conversations/{id}/...` endpoints unchanged — a direct
 * conversation id IS a conversation id. It deliberately does NOT appear in
 * `GET /api/conversations` (see `MessagingConversationRepository::list()`) —
 * `GET /api/direct-conversations` is the only list surface for a member's
 * direct conversations, scoped exactly like `GET /api/channels` (an INNER
 * JOIN on `messaging_participants`), which is what preserves the privacy
 * invariant: a member can never discover a direct conversation they are not
 * a participant of, through either list.
 *
 * @category Resource
 *
 * @version 1.1.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'DirectConversation',
  operations: [
    new Post(
      uriTemplate: '/direct-conversations',
      input: GetOrCreateDirectConversationInput::class,
      output: ConversationOutput::class,
      processor: GetOrCreateDirectConversationProcessor::class,
      status: Response::HTTP_OK,
      security: "is_granted('ROLE_USER')",
    ),
    new GetCollection(
      uriTemplate: '/direct-conversations',
      output: ConversationOutput::class,
      provider: ListDirectConversationsProvider::class,
      paginationEnabled: true,
      paginationClientItemsPerPage: true,
      paginationMaximumItemsPerPage: 100,
      paginationItemsPerPage: 30,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(parameters: [
        new Parameter(name: 'organization', in: 'query', description: 'Organization IRI.', required: true, schema: ['type' => 'string']),
        new Parameter(name: 'isArchived', in: 'query', description: 'Archived-flag filter.', required: false, schema: ['type' => 'boolean']),
      ]),
    ),
  ],
)]
final class DirectConversationResource
{
}
