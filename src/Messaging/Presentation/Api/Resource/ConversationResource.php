<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Delete, Get, GetCollection, Patch, Post};
use ApiPlatform\OpenApi\Model\{Operation, Parameter};
use Messaging\Presentation\Api\Dto\Input\{ArchiveConversationInput, GetOrCreateConversationInput, MarkConversationReadInput};
use Messaging\Presentation\Api\Dto\Output\{ConversationOutput, MessagingSubscriptionOutput};
use Messaging\Presentation\Api\Processor\Conversation\{ArchiveConversationProcessor, FavoriteConversationProcessor, GetOrCreateConversationProcessor, UnfavoriteConversationProcessor};
use Messaging\Presentation\Api\Processor\ReadMarker\MarkConversationReadProcessor;
use Messaging\Presentation\Api\Provider\Conversation\{GetConversationProvider, ListConversationsProvider};
use Messaging\Presentation\Api\Provider\Subscription\GetMessagingSubscriptionProvider;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resource ConversationResource.
 *
 * Contextual discussion threads bound one-to-one to a core-entity subject
 * (facility | equipment | intervention | non-conformity in v1). Every
 * operation requires `ROLE_USER` at the resource level; the finer-grained
 * `organization.messaging.{read,write,manage}` + subject-permission checks
 * are enforced in the application layer (mirrors Maintenance/Intervention).
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'Conversation',
  operations: [
    new GetCollection(
      uriTemplate: '/conversations',
      output: ConversationOutput::class,
      provider: ListConversationsProvider::class,
      paginationEnabled: true,
      paginationClientItemsPerPage: true,
      paginationItemsPerPage: 30,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(parameters: [
        new Parameter(name: 'organization', in: 'query', description: 'Organization IRI.', required: true, schema: ['type' => 'string']),
        new Parameter(name: 'subjectType', in: 'query', description: 'Subject type filter (facility|equipment|intervention|non_conformity).', required: false, schema: ['type' => 'string']),
        new Parameter(name: 'subjectId', in: 'query', description: 'Subject identifier filter.', required: false, schema: ['type' => 'string']),
        new Parameter(name: 'isArchived', in: 'query', description: 'Archived-flag filter.', required: false, schema: ['type' => 'boolean']),
        new Parameter(name: 'unreadOnly', in: 'query', description: 'Only return conversations with unread activity.', required: false, schema: ['type' => 'boolean']),
      ]),
    ),
    new Post(
      uriTemplate: '/conversations',
      input: GetOrCreateConversationInput::class,
      output: ConversationOutput::class,
      processor: GetOrCreateConversationProcessor::class,
      security: "is_granted('ROLE_USER')",
    ),
    new Get(
      uriTemplate: '/conversations/{id}',
      output: ConversationOutput::class,
      provider: GetConversationProvider::class,
      security: "is_granted('ROLE_USER')",
    ),
    new Patch(
      uriTemplate: '/conversations/{id}',
      input: ArchiveConversationInput::class,
      output: ConversationOutput::class,
      processor: ArchiveConversationProcessor::class,
      security: "is_granted('ROLE_USER')",
    ),
    new Patch(
      name: 'messaging_mark_conversation_read',
      uriTemplate: '/conversations/{id}/read',
      input: MarkConversationReadInput::class,
      output: ConversationOutput::class,
      processor: MarkConversationReadProcessor::class,
      security: "is_granted('ROLE_USER')",
    ),
    new Get(
      name: 'messaging_conversation_subscription',
      uriTemplate: '/conversations/{id}/subscription',
      output: MessagingSubscriptionOutput::class,
      provider: GetMessagingSubscriptionProvider::class,
      security: "is_granted('ROLE_USER')",
    ),
    // L1.5: favorite conversations (sidebar ordering only — a channel id IS
    // a conversation id, so these ALSO cover channels, unlike the pin/save
    // operations which live on MessageResource).
    new Post(
      name: 'favorite_conversation',
      uriTemplate: '/conversations/{id}/favorite',
      read: false,
      input: false,
      output: ConversationOutput::class,
      processor: FavoriteConversationProcessor::class,
      status: Response::HTTP_OK,
      security: "is_granted('ROLE_USER')",
    ),
    new Delete(
      name: 'unfavorite_conversation',
      uriTemplate: '/conversations/{id}/favorite',
      read: false,
      output: false,
      processor: UnfavoriteConversationProcessor::class,
      status: Response::HTTP_NO_CONTENT,
      security: "is_granted('ROLE_USER')",
    ),
  ],
)]
final class ConversationResource
{
}
