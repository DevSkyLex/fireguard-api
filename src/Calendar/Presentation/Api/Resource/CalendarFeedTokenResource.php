<?php

declare(strict_types=1);

namespace Calendar\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Delete, Get, Post};
use ApiPlatform\OpenApi\Model\{Operation, Response};
use Calendar\Presentation\Api\Dto\Output\FeedToken\{CalendarFeedTokenOutput, CalendarFeedTokenSecretOutput};
use Calendar\Presentation\Api\Operation\CalendarOperations;
use Calendar\Presentation\Api\Processor\FeedToken\{RevokeCalendarFeedTokenProcessor, RotateCalendarFeedTokenProcessor};
use Calendar\Presentation\Api\Provider\FeedToken\GetCalendarFeedTokenProvider;
use Calendar\Presentation\Api\Serialization\CalendarSerializationGroup;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Resource CalendarFeedTokenResource.
 *
 * The acting member's personal iCal subscription token for one
 * organization: at most one active token per (organization, member) pair.
 * POST creates OR regenerates (the previous token is revoked) and is the
 * only response ever carrying the raw secret; GET returns metadata without
 * the secret; DELETE revokes. The public `.ics` endpoint the secret unlocks
 * lives on {@see CalendarFeedIcsResource}.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'CalendarFeedToken',
  routePrefix: '/organizations',
  description: 'Member-scoped iCal calendar feed subscription token.',
  operations: [
    new Post(
      name: CalendarOperations::CREATE_CALENDAR_FEED_TOKEN,
      uriTemplate: '/{organizationId}/calendar/feed-token',
      status: HttpResponse::HTTP_CREATED,
      input: false,
      output: CalendarFeedTokenSecretOutput::class,
      processor: RotateCalendarFeedTokenProcessor::class,
      normalizationContext: ['groups' => [CalendarSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Calendar'],
        summary: 'Create or regenerate the member calendar feed token',
        description: 'Creates the acting member\'s iCal feed token, revoking any previously active one. '
          . 'The response is the ONLY time the raw secret (and the complete feed URL) is returned — the backend stores its SHA-256 hash. '
          . 'Requires organization.events.read.',
        responses: [
          HttpResponse::HTTP_CREATED => new Response(description: 'Token created; the secret is shown this one time'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
        ],
      ),
    ),
    new Get(
      name: CalendarOperations::GET_CALENDAR_FEED_TOKEN,
      uriTemplate: '/{organizationId}/calendar/feed-token',
      output: CalendarFeedTokenOutput::class,
      provider: GetCalendarFeedTokenProvider::class,
      normalizationContext: ['groups' => [CalendarSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Calendar'],
        summary: 'Get the member calendar feed token metadata',
        description: 'Metadata without the secret: creation date and last recorded use (persisted at most once per hour). 404 when the member has no active token.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Token metadata'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'No active token for this member'),
        ],
      ),
    ),
    new Delete(
      name: CalendarOperations::DELETE_CALENDAR_FEED_TOKEN,
      uriTemplate: '/{organizationId}/calendar/feed-token',
      status: HttpResponse::HTTP_NO_CONTENT,
      read: false,
      input: false,
      output: false,
      processor: RevokeCalendarFeedTokenProcessor::class,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Calendar'],
        summary: 'Revoke the member calendar feed token',
        description: 'Immediately invalidates the feed URL. 404 when the member has no active token.',
        responses: [
          HttpResponse::HTTP_NO_CONTENT => new Response(description: 'Token revoked'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'No active token for this member'),
        ],
      ),
    ),
  ],
)]
final class CalendarFeedTokenResource
{
}
