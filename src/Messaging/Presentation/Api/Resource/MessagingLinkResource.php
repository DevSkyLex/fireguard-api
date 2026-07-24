<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, GetCollection};
use ApiPlatform\OpenApi\Model\{Operation, Parameter};
use Messaging\Presentation\Api\Dto\Output\MessagingLinkOutput;
use Messaging\Presentation\Api\Provider\Link\ListConversationLinksProvider;

/**
 * Resource MessagingLinkResource.
 *
 * URLs extracted from message bodies (B2) — the conversation Links tab.
 * Same access rule as `GET /conversations/{conversationId}/messages` (see
 * `ListConversationLinksHandler`).
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'MessagingLink',
  description: 'URLs extracted from message bodies, most recently posted first.',
  operations: [
    new GetCollection(
      name: 'list_conversation_links',
      uriTemplate: '/conversations/{conversationId}/links',
      output: MessagingLinkOutput::class,
      provider: ListConversationLinksProvider::class,
      paginationEnabled: true,
      paginationClientItemsPerPage: true,
      paginationItemsPerPage: 30,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(parameters: [
        new Parameter(name: 'page', in: 'query', required: false, schema: ['type' => 'integer']),
        new Parameter(name: 'itemsPerPage', in: 'query', required: false, schema: ['type' => 'integer']),
      ]),
    ),
  ],
)]
final class MessagingLinkResource
{
}
