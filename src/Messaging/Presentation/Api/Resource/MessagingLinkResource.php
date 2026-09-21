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
      paginationMaximumItemsPerPage: 100,
      paginationItemsPerPage: 30,
      security: "is_granted('ROLE_USER')",
      parameters: [
        'page' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'integer'],
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(name: 'page', in: 'query', required: false, schema: ['type' => 'integer']),
        ),
        'itemsPerPage' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'integer'],
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(name: 'itemsPerPage', in: 'query', required: false, schema: ['type' => 'integer']),
        ),
      ],
      openapi: new Operation(parameters: []),
    ),
  ],
)]
final class MessagingLinkResource
{
}
