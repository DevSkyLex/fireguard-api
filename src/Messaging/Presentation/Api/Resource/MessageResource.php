<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Delete, GetCollection, Patch, Post, Put};
use ApiPlatform\OpenApi\Model\{Operation, Parameter, Response as OpenApiResponse};
use Messaging\Presentation\Api\Dto\Input\{AddReactionInput, EditMessageInput, PostMessageInput, PostReplyInput};
use Messaging\Presentation\Api\Dto\Output\MessageOutput;
use Messaging\Presentation\Api\Processor\Message\{AddReactionProcessor, DeleteMessageProcessor, EditMessageProcessor, PinMessageProcessor, PostMessageProcessor, PostReplyProcessor, PutMessageProcessor, RemoveReactionProcessor, SaveMessageProcessor, UnpinMessageProcessor, UnsaveMessageProcessor};
use Messaging\Presentation\Api\Provider\Message\{ListMessagesProvider, ListPinnedMessagesProvider, ListRepliesProvider, ListSavedMessagesProvider};
use Symfony\Component\HttpFoundation\Response;

/**
 * Resource MessageResource.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'Message',
  operations: [
    new GetCollection(
      uriTemplate: '/conversations/{conversationId}/messages',
      output: MessageOutput::class,
      provider: ListMessagesProvider::class,
      paginationEnabled: true,
      paginationClientItemsPerPage: true,
      paginationMaximumItemsPerPage: 100,
      paginationItemsPerPage: 30,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(parameters: [
        new Parameter(name: 'page', in: 'query', required: false, schema: ['type' => 'integer']),
        new Parameter(name: 'itemsPerPage', in: 'query', required: false, schema: ['type' => 'integer']),
      ]),
    ),
    new Post(
      uriTemplate: '/conversations/{conversationId}/messages',
      input: PostMessageInput::class,
      output: MessageOutput::class,
      processor: PostMessageProcessor::class,
      status: Response::HTTP_CREATED,
      security: "is_granted('ROLE_USER')",
    ),
    // Idempotent, create-only twin of the POST above: the client owns the id,
    // so replaying a queued offline send conflicts instead of duplicating.
    new Put(
      name: 'put_message',
      uriTemplate: '/conversations/{conversationId}/messages/{clientId}',
      read: false,
      input: PostMessageInput::class,
      output: MessageOutput::class,
      processor: PutMessageProcessor::class,
      status: Response::HTTP_CREATED,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(responses: [
        Response::HTTP_CREATED => new OpenApiResponse(description: 'Message created'),
        Response::HTTP_CONFLICT => new OpenApiResponse(description: 'This client identifier was already used'),
        Response::HTTP_PRECONDITION_REQUIRED => new OpenApiResponse(description: 'If-None-Match: * is required'),
        Response::HTTP_PRECONDITION_FAILED => new OpenApiResponse(description: 'If-None-Match must be *'),
      ]),
    ),
    new Patch(
      uriTemplate: '/messages/{id}',
      input: EditMessageInput::class,
      output: MessageOutput::class,
      processor: EditMessageProcessor::class,
      security: "is_granted('ROLE_USER')",
    ),
    new Delete(
      uriTemplate: '/messages/{id}',
      output: false,
      processor: DeleteMessageProcessor::class,
      status: Response::HTTP_NO_CONTENT,
      security: "is_granted('ROLE_USER')",
    ),
    new GetCollection(
      name: 'list_pinned_messages',
      uriTemplate: '/conversations/{conversationId}/pinned-messages',
      output: MessageOutput::class,
      provider: ListPinnedMessagesProvider::class,
      paginationEnabled: true,
      paginationClientItemsPerPage: true,
      paginationMaximumItemsPerPage: 100,
      paginationItemsPerPage: 30,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(parameters: [
        new Parameter(name: 'page', in: 'query', required: false, schema: ['type' => 'integer']),
        new Parameter(name: 'itemsPerPage', in: 'query', required: false, schema: ['type' => 'integer']),
      ]),
    ),
    new Post(
      name: 'pin_message',
      uriTemplate: '/messages/{id}/pin',
      read: false,
      input: false,
      output: MessageOutput::class,
      processor: PinMessageProcessor::class,
      status: Response::HTTP_OK,
      security: "is_granted('ROLE_USER')",
    ),
    new Delete(
      name: 'unpin_message',
      uriTemplate: '/messages/{id}/pin',
      read: false,
      output: false,
      processor: UnpinMessageProcessor::class,
      status: Response::HTTP_NO_CONTENT,
      security: "is_granted('ROLE_USER')",
    ),
    new Post(
      name: 'add_reaction',
      uriTemplate: '/messages/{id}/reactions',
      read: false,
      input: AddReactionInput::class,
      output: MessageOutput::class,
      processor: AddReactionProcessor::class,
      status: Response::HTTP_OK,
      security: "is_granted('ROLE_USER')",
    ),
    new Delete(
      name: 'remove_reaction',
      uriTemplate: '/messages/{id}/reactions/{emoji}',
      read: false,
      output: false,
      processor: RemoveReactionProcessor::class,
      status: Response::HTTP_NO_CONTENT,
      security: "is_granted('ROLE_USER')",
    ),
    // L2.5: threaded replies. `{id}` is the PARENT (root) message; a reply
    // to a reply is refused (single-level threading, see `PostReplyHandler`).
    new Post(
      name: 'post_reply',
      uriTemplate: '/messages/{id}/replies',
      input: PostReplyInput::class,
      output: MessageOutput::class,
      processor: PostReplyProcessor::class,
      status: Response::HTTP_CREATED,
      security: "is_granted('ROLE_USER')",
    ),
    new GetCollection(
      name: 'list_replies',
      uriTemplate: '/messages/{id}/replies',
      output: MessageOutput::class,
      provider: ListRepliesProvider::class,
      paginationEnabled: true,
      paginationClientItemsPerPage: true,
      paginationMaximumItemsPerPage: 100,
      paginationItemsPerPage: 30,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(parameters: [
        new Parameter(name: 'page', in: 'query', required: false, schema: ['type' => 'integer']),
        new Parameter(name: 'itemsPerPage', in: 'query', required: false, schema: ['type' => 'integer']),
      ]),
    ),
    // L1.5: saved messages (private bookmarks, never a property of the
    // conversation — contrast with the pin operations above).
    new GetCollection(
      name: 'list_saved_messages',
      uriTemplate: '/saved-messages',
      output: MessageOutput::class,
      provider: ListSavedMessagesProvider::class,
      paginationEnabled: true,
      paginationClientItemsPerPage: true,
      paginationMaximumItemsPerPage: 100,
      paginationItemsPerPage: 30,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(parameters: [
        new Parameter(name: 'organization', in: 'query', description: 'Organization IRI.', required: true, schema: ['type' => 'string']),
        new Parameter(name: 'page', in: 'query', required: false, schema: ['type' => 'integer']),
        new Parameter(name: 'itemsPerPage', in: 'query', required: false, schema: ['type' => 'integer']),
      ]),
    ),
    new Post(
      name: 'save_message',
      uriTemplate: '/messages/{id}/save',
      read: false,
      input: false,
      output: MessageOutput::class,
      processor: SaveMessageProcessor::class,
      status: Response::HTTP_OK,
      security: "is_granted('ROLE_USER')",
    ),
    new Delete(
      name: 'unsave_message',
      uriTemplate: '/messages/{id}/save',
      read: false,
      output: false,
      processor: UnsaveMessageProcessor::class,
      status: Response::HTTP_NO_CONTENT,
      security: "is_granted('ROLE_USER')",
    ),
  ],
)]
final class MessageResource
{
}
