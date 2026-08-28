<?php

declare(strict_types=1);

namespace Calendar\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get};
use ApiPlatform\OpenApi\Model\{Operation, Response};
use Calendar\Presentation\Api\Controller\GetCalendarFeedIcsController;
use Calendar\Presentation\Api\Operation\CalendarOperations;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Resource CalendarFeedIcsResource.
 *
 * The public iCal subscription endpoint Outlook/Google/Apple Calendar poll:
 * no session, no Bearer token — the URL-embedded secret is the credential
 * (`PUBLIC_ACCESS` in `config/packages/security.yaml`, mirroring the public
 * invitation preview). Serves the token member's unified feed (their
 * permissions apply) over a fixed window of 30 days back / 180 days ahead,
 * as a hand-written RFC 5545 document. Wired via `controller:` with
 * `read`/`write`/`serialize`/`output` disabled, the same mechanism as
 * `Compliance`'s download endpoints, because the payload is `text/calendar`,
 * not JSON.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'CalendarFeedIcs',
  description: 'Public member iCal subscription feed (secret-URL authenticated).',
  operations: [
    new Get(
      name: CalendarOperations::GET_CALENDAR_FEED_ICS,
      uriTemplate: '/calendar/feed/{token}.ics',
      controller: GetCalendarFeedIcsController::class,
      read: false,
      write: false,
      deserialize: false,
      serialize: false,
      output: false,
      openapi: new Operation(
        tags: ['Calendar'],
        summary: 'Subscribe to a member calendar feed (iCalendar)',
        description: 'Unauthenticated by design: the URL-embedded token secret is the credential. '
          . 'Returns the token member\'s unified feed (events, inspections, interventions, maintenance due dates) '
          . 'for a fixed window of 30 days back / 180 days ahead as an RFC 5545 text/calendar document. '
          . 'Unknown and revoked tokens both answer a plain 404.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'iCalendar feed (text/calendar)'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Unknown or revoked token'),
        ],
      ),
    ),
  ],
)]
final class CalendarFeedIcsResource
{
}
