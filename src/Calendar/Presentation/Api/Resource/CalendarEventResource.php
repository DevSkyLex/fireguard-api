<?php

declare(strict_types=1);

namespace Calendar\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Delete, Get, Patch, Post};
use ApiPlatform\OpenApi\Model\{Operation, Response};
use Calendar\Presentation\Api\Dto\Input\Event\{CreateCalendarEventInput, UpdateCalendarEventInput};
use Calendar\Presentation\Api\Dto\Output\Event\CalendarEventOutput;
use Calendar\Presentation\Api\Operation\CalendarOperations;
use Calendar\Presentation\Api\Processor\Event\{CreateCalendarEventProcessor, DeleteCalendarEventProcessor, UpdateCalendarEventProcessor};
use Calendar\Presentation\Api\Provider\Event\GetCalendarEventProvider;
use Calendar\Presentation\Api\Serialization\CalendarSerializationGroup;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Resource CalendarEventResource.
 *
 * Standalone, organization-scoped calendar event CRUD. This is what makes
 * the calendar UI's "New event" button work; see the sibling
 * `CalendarFeedResource` for the unified, merged view (standalone events +
 * inspections + interventions + preventive-maintenance due dates).
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'CalendarEvent',
  routePrefix: '/organizations',
  description: 'Organization-scoped standalone calendar event management.',
  operations: [
    new Post(
      name: CalendarOperations::CREATE_CALENDAR_EVENT,
      uriTemplate: '/{organizationId}/calendar/events',
      status: HttpResponse::HTTP_CREATED,
      input: CreateCalendarEventInput::class,
      output: CalendarEventOutput::class,
      processor: CreateCalendarEventProcessor::class,
      denormalizationContext: ['groups' => [CalendarSerializationGroup::WRITE]],
      normalizationContext: ['groups' => [CalendarSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Calendar'],
        summary: 'Create calendar event',
        description: 'Creates a standalone organization calendar event. Requires organization.events.write.',
        responses: [
          HttpResponse::HTTP_CREATED => new Response(description: 'Event created'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
          HttpResponse::HTTP_UNPROCESSABLE_ENTITY => new Response(description: 'Invalid dates, or no active membership for the acting user'),
        ],
      ),
    ),
    new Get(
      name: CalendarOperations::GET_CALENDAR_EVENT,
      uriTemplate: '/{organizationId}/calendar/events/{eventId}',
      output: CalendarEventOutput::class,
      provider: GetCalendarEventProvider::class,
      normalizationContext: ['groups' => [CalendarSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Calendar'],
        summary: 'Get calendar event',
        description: 'Returns a single standalone calendar event. Requires organization.events.read.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Event retrieved'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Organization or event not found'),
        ],
      ),
    ),
    new Patch(
      name: CalendarOperations::UPDATE_CALENDAR_EVENT,
      uriTemplate: '/{organizationId}/calendar/events/{eventId}',
      read: false,
      input: UpdateCalendarEventInput::class,
      output: CalendarEventOutput::class,
      processor: UpdateCalendarEventProcessor::class,
      denormalizationContext: ['groups' => [CalendarSerializationGroup::WRITE]],
      normalizationContext: ['groups' => [CalendarSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Calendar'],
        summary: 'Update calendar event',
        description: 'Partially updates a standalone calendar event (title, description, startsAt, endsAt, allDay, facilityId). Requires organization.events.write.',
      ),
    ),
    new Delete(
      name: CalendarOperations::DELETE_CALENDAR_EVENT,
      uriTemplate: '/{organizationId}/calendar/events/{eventId}',
      read: false,
      input: false,
      output: false,
      processor: DeleteCalendarEventProcessor::class,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Calendar'],
        summary: 'Delete calendar event',
        description: 'Permanently deletes a standalone calendar event. Requires organization.events.write.',
        responses: [
          HttpResponse::HTTP_NO_CONTENT => new Response(description: 'Event deleted'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Organization or event not found'),
        ],
      ),
    ),
  ],
)]
final class CalendarEventResource
{
}
