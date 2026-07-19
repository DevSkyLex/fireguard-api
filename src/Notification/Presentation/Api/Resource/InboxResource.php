<?php

declare(strict_types=1);

namespace Notification\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get};
use ApiPlatform\OpenApi\Model\{Operation, Parameter, Response};
use Notification\Presentation\Api\Dto\Output\Inbox\InboxOutput;
use Notification\Presentation\Api\Operation\NotificationOperations;
use Notification\Presentation\Api\Provider\Inbox\GetInboxProvider;
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
      openapi: new Operation(
        tags: ['Inbox'],
        summary: 'Get the unified inbox feed',
        description: 'Returns a reverse-chronological, cursor-paginated feed merging every registered inbox source for the authenticated user (currently: their own notifications). '
          . 'Pagination is cursor-based ONLY (`before`, an ISO-8601 instant) — there is no `page`/`offset` parameter. '
          . 'Offset pagination over a merged heterogeneous feed is unstable by construction: concurrent writes to any source shift the window and produce duplicated or skipped rows. '
          . 'To fetch the next page, pass the previous response\'s `nextCursor` as `before`; stop once `hasMore` is false.',
        security: [['bearerAuth' => []]],
        parameters: [
          new Parameter(
            name: 'organization',
            in: 'query',
            required: false,
            description: 'Filter by organization identifier. When omitted, items across all organizations (and account-level ones) are returned.',
            schema: ['type' => 'string', 'format' => 'uuid'],
          ),
          new Parameter(
            name: 'before',
            in: 'query',
            required: false,
            description: 'Cursor: only items that occurred strictly before this ISO-8601 instant are returned. Omit for the first page; pass the previous response\'s `nextCursor` to fetch the next page.',
            schema: ['type' => 'string', 'format' => 'date-time'],
          ),
          new Parameter(
            name: 'limit',
            in: 'query',
            required: false,
            description: 'Maximum number of items to return (clamped server-side between 1 and 50).',
            schema: ['type' => 'integer', 'default' => 20],
          ),
        ],
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Inbox feed retrieved successfully'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Invalid "before" cursor'),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(description: 'Authentication required'),
        ],
      ),
    ),
  ],
)]
final class InboxResource
{
}
