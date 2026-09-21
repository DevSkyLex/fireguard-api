<?php

declare(strict_types=1);

namespace Notification\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get};
use ApiPlatform\OpenApi\Model\{Operation, Parameter, Response};
use Notification\Presentation\Api\Dto\Output\Inbox\{InboxOutput, InboxUnreadCountOutput};
use Notification\Presentation\Api\Operation\NotificationOperations;
use Notification\Presentation\Api\Provider\Inbox\{GetInboxProvider, GetInboxUnreadCountProvider};
use Notification\Presentation\Api\Serialization\NotificationSerializationGroup;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Resource InboxResource.
 *
 * Unified inbox feed: merges every tagged `inbox.source_provider` adapter
 * (Notification's own notifications today; mentions, direct messages and
 * thread replies from Messaging as a later, separate lot) into a single
 * reverse-chronological feed for the authenticated user. A dedicated
 * top-level resource (not nested under `/notifications`) so its cursor
 * pagination contract never collides with that resource's `/{id}` route.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'Inbox',
  routePrefix: '/inbox',
  description: 'Unified feed of things needing the authenticated user\'s attention, merged from every registered source.',
  operations: [
    new Get(
      name: NotificationOperations::GET_INBOX,
      uriTemplate: '',
      input: false,
      output: InboxOutput::class,
      provider: GetInboxProvider::class,
      normalizationContext: ['groups' => [NotificationSerializationGroup::INBOX_READ]],
      security: "is_granted('ROLE_USER')",
      parameters: [
        'cursor' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string', 'maxLength' => 2048],
          description: 'Opaque nextPageCursor from the previous response; preserves timestamp ties across all sources. Mutually exclusive with before.',
          castToArray: false,
        ),
        'organization' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string', 'format' => 'uuid'],
          description: 'Filter by organization identifier. When omitted, items across all organizations (and account-level ones) are returned.',
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(
            name: 'organization',
            in: 'query',
            required: false,
            description: 'Filter by organization identifier. When omitted, items across all organizations (and account-level ones) are returned.',
            schema: ['type' => 'string', 'format' => 'uuid'],
          ),
        ),
        'before' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string', 'format' => 'date-time'],
          description: 'Cursor: only items that occurred strictly before this ISO-8601 instant are returned. Omit for the first page; pass the previous response\'s `nextCursor` to fetch the next page.',
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(
            name: 'before',
            in: 'query',
            required: false,
            description: 'Cursor: only items that occurred strictly before this ISO-8601 instant are returned. Omit for the first page; pass the previous response\'s `nextCursor` to fetch the next page.',
            schema: ['type' => 'string', 'format' => 'date-time'],
          ),
        ),
        'limit' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'integer'],
          description: 'Maximum number of items to return (clamped server-side between 1 and 50).',
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(
            name: 'limit',
            in: 'query',
            required: false,
            description: 'Maximum number of items to return (clamped server-side between 1 and 50).',
            schema: ['type' => 'integer', 'default' => 20],
          ),
        ),
      ],
      openapi: new Operation(
        tags: ['Inbox'],
        summary: 'Get the unified inbox feed',
        description: 'Merges account notifications and, when an organization is selected, authorized Messaging mentions. '
          . 'Send nextPageCursor unchanged as cursor to continue the timestamp/source/id ordering without skipping ties. '
          . 'If complete is false, retry the current page; no nextPageCursor is issued while a source is unavailable. '
          . 'Legacy before/nextCursor remain supported but do not preserve timestamp ties. Do not combine before and cursor.',
        security: [['bearerAuth' => []]],
        parameters: [],
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Inbox feed retrieved successfully'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Invalid or conflicting cursor parameters'),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(description: 'Authentication required'),
        ],
      ),
    ),
    new Get(
      name: NotificationOperations::GET_INBOX_UNREAD_COUNT,
      uriTemplate: '/unread-count',
      input: false,
      output: InboxUnreadCountOutput::class,
      provider: GetInboxUnreadCountProvider::class,
      normalizationContext: ['groups' => [NotificationSerializationGroup::INBOX_UNREAD_COUNT]],
      security: "is_granted('ROLE_USER')",
      parameters: [
        'organization' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string', 'format' => 'uuid'],
          description: 'Filter by organization identifier. When omitted, unread items across all organizations (and account-level ones) are counted.',
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(
            name: 'organization',
            in: 'query',
            required: false,
            description: 'Filter by organization identifier. When omitted, unread items across all organizations (and account-level ones) are counted.',
            schema: ['type' => 'string', 'format' => 'uuid'],
          ),
        ),
      ],
      openapi: new Operation(
        tags: ['Inbox'],
        summary: 'Get the unified inbox unread count',
        description: 'Returns the unread item count summed across every registered inbox source for the authenticated user (currently: their own notifications — the same figure `GET /notifications/unread-count` already exposes, but sourced through the unified inbox seam so it stays correct once Messaging registers mentions/direct messages/thread replies as additional sources). Intended for a persistent UI chrome badge, unlike the paginated `GET /inbox` feed.',
        security: [['bearerAuth' => []]],
        parameters: [],
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Unread inbox count returned successfully'),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(description: 'Authentication required'),
        ],
      ),
    ),
  ],
)]
final class InboxResource
{
}
