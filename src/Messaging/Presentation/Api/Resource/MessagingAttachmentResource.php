<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Delete, GetCollection, Post};
use ApiPlatform\OpenApi\Model\{Operation, Parameter, Response};
use Messaging\Presentation\Api\Dto\Output\MessageAttachmentOutput;
use Messaging\Presentation\Api\Processor\Attachment\MessagingMediaProcessor;
use Messaging\Presentation\Api\Provider\Attachment\MessagingMediaProvider;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Resource MessagingAttachmentResource.
 *
 * Files posted alongside a message, and — via
 * `/conversations/{conversationId}/attachments` — the conversation Files
 * tab. Both surfaces share the same `messaging_attachments` table (see
 * `src/Messaging/MODULE.md`).
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'MessagingAttachment',
  description: 'File attachments posted alongside a message.',
  operations: [
    new Post(
      name: 'add_message_attachment',
      uriTemplate: '/messages/{messageId}/attachments',
      deserialize: false,
      input: false,
      output: MessageAttachmentOutput::class,
      processor: MessagingMediaProcessor::class,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Messaging'],
        summary: 'Upload a message attachment',
        description: 'Uploads a multipart file attachment to a message.',
        responses: [
          HttpResponse::HTTP_CREATED => new Response(description: 'Attachment uploaded'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Invalid input'),
          HttpResponse::HTTP_UNPROCESSABLE_ENTITY => new Response(description: 'MIME type, size, or message state rejected'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Message not found'),
        ],
      ),
    ),
    new GetCollection(
      name: 'list_conversation_attachments',
      uriTemplate: '/conversations/{conversationId}/attachments',
      input: false,
      output: MessageAttachmentOutput::class,
      provider: MessagingMediaProvider::class,
      paginationEnabled: true,
      paginationClientItemsPerPage: true,
      paginationMaximumItemsPerPage: 100,
      paginationItemsPerPage: 30,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Messaging'],
        summary: 'List a conversation\'s attachments (Files tab)',
        description: 'Lists the attachments posted in a conversation, most recently uploaded first.',
        parameters: [
          new Parameter(name: 'page', in: 'query', required: false, schema: ['type' => 'integer']),
          new Parameter(name: 'itemsPerPage', in: 'query', required: false, schema: ['type' => 'integer']),
        ],
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Attachments retrieved'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Conversation not found'),
        ],
      ),
    ),
    new Delete(
      name: 'delete_message_attachment',
      uriTemplate: '/messaging-attachments/{id}',
      read: false,
      input: false,
      output: false,
      processor: MessagingMediaProcessor::class,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Messaging'],
        summary: 'Delete a message attachment',
        responses: [
          HttpResponse::HTTP_NO_CONTENT => new Response(description: 'Attachment deleted'),
          HttpResponse::HTTP_PRECONDITION_REQUIRED => new Response(description: 'If-Match header is required'),
          HttpResponse::HTTP_PRECONDITION_FAILED => new Response(description: 'Attachment revision is stale'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Attachment not found'),
        ],
      ),
    ),
  ],
)]
final class MessagingAttachmentResource
{
}
