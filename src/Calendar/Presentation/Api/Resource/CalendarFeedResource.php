<?php

declare(strict_types=1);

namespace Calendar\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get};
use ApiPlatform\OpenApi\Model\{Operation, Parameter, Response};
use Calendar\Presentation\Api\Dto\Output\Feed\CalendarFeedOutput;
use Calendar\Presentation\Api\Operation\CalendarOperations;
use Calendar\Presentation\Api\Provider\Feed\GetCalendarFeedProvider;
use Calendar\Presentation\Api\Serialization\CalendarSerializationGroup;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Resource CalendarFeedResource.
 *
 * The unified organization calendar feed: standalone events merged with
 * inspections, interventions, and preventive-maintenance due dates, bounded
 * to a mandatory, capped date range. The mockup's fourth category, "Audit",
 * has no business existence in this backend — see `Calendar\MODULE.md`.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'CalendarFeed',
  routePrefix: '/organizations',
  description: 'Unified organization calendar feed (standalone events, inspections, interventions, preventive-maintenance due dates).',
  operations: [
    new Get(
      name: CalendarOperations::GET_CALENDAR_FEED,
      uriTemplate: '/{organizationId}/calendar/feed',
      input: false,
      output: CalendarFeedOutput::class,
      provider: GetCalendarFeedProvider::class,
      normalizationContext: ['groups' => [CalendarSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Calendar'],
        summary: 'Get unified calendar feed',
        description: 'Returns the merged, chronologically ordered feed for a mandatory, bounded (max 366 days) date range. '
          . 'Merges standalone calendar events with inspections, interventions, and preventive-maintenance due dates. '
          . 'A failing source is isolated and logged rather than failing the whole request. Requires organization.events.read.',
        parameters: [
          new Parameter(
            name: 'from',
            in: 'query',
            required: true,
            description: 'Inclusive ISO 8601 datetime lower bound, with an explicit timezone offset.',
            schema: ['type' => 'string', 'format' => 'date-time', 'example' => '2026-08-01T00:00:00Z'],
          ),
          new Parameter(
            name: 'to',
            in: 'query',
            required: true,
            description: 'Inclusive ISO 8601 datetime upper bound, with an explicit timezone offset. The range cannot exceed 366 days.',
            schema: ['type' => 'string', 'format' => 'date-time', 'example' => '2026-08-31T23:59:59Z'],
          ),
        ],
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Feed retrieved'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Missing, malformed, inverted, or over-long "from"/"to" range'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
        ],
      ),
    ),
  ],
)]
final class CalendarFeedResource
{
}
