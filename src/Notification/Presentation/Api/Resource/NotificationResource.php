<?php

declare(strict_types=1);

namespace Notification\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get, GetCollection, Patch};
use ApiPlatform\OpenApi\Model\{Operation, Parameter, Response};
use Notification\Presentation\Api\Dto\Input\NotificationPreference\UpdateNotificationPreferencesInput;
use Notification\Presentation\Api\Dto\Output\MercureSubscription\MercureSubscriptionOutput;
use Notification\Presentation\Api\Dto\Output\Notification\{MarkAllNotificationsAsReadOutput, NotificationOutput, UnreadNotificationsCountOutput};
use Notification\Presentation\Api\Dto\Output\NotificationPreference\NotificationPreferencesOutput;
use Notification\Presentation\Api\Operation\NotificationOperations;
use Notification\Presentation\Api\Processor\Notification\{MarkAllNotificationsAsReadProcessor, MarkNotificationAsReadProcessor};
use Notification\Presentation\Api\Processor\NotificationPreference\UpdateNotificationPreferencesProcessor;
use Notification\Presentation\Api\Provider\MercureSubscription\GetMercureSubscriptionProvider;
use Notification\Presentation\Api\Provider\Notification\{GetNotificationProvider, GetUnreadNotificationsCountProvider, ListNotificationsProvider};
use Notification\Presentation\Api\Provider\NotificationPreference\GetNotificationPreferencesProvider;
use Notification\Presentation\Api\Serialization\NotificationSerializationGroup;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Resource NotificationResource.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'Notification',
  routePrefix: '/notifications',
  description: 'User notifications delivered by internal modules.',
  operations: [
    new GetCollection(
      name: NotificationOperations::LIST,
      uriTemplate: '',
      input: false,
      output: NotificationOutput::class,
      provider: ListNotificationsProvider::class,
      paginationEnabled: true,
      paginationClientItemsPerPage: true,
      paginationMaximumItemsPerPage: 100,
      paginationItemsPerPage: 20,
      normalizationContext: ['groups' => [NotificationSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Notification'],
        summary: 'List notifications',
        description: 'Returns a paginated collection of notifications for the authenticated user. Read notifications from low-value categories may be omitted from the default list after a retention delay.',
        security: [['bearerAuth' => []]],
        parameters: [
          new Parameter(
            name: 'unreadOnly',
            in: 'query',
            required: false,
            description: 'When true, returns only unread notifications.',
            schema: ['type' => 'boolean'],
          ),
          new Parameter(
            name: 'type',
            in: 'query',
            required: false,
            description: 'Filter by exact notification type (e.g. `organization.invitation`). See `GET /notification-types` for the full list.',
            schema: ['type' => 'string'],
          ),
          new Parameter(
            name: 'category',
            in: 'query',
            required: false,
            description: 'Filter by category prefix (e.g. `organization`, `system`). Ignored when `type` is also provided.',
            schema: ['type' => 'string'],
          ),
          new Parameter(
            name: 'organization',
            in: 'query',
            required: false,
            description: 'Filter by organization identifier. When omitted, notifications across all organizations are returned, including account-level notifications that have no organization.',
            schema: ['type' => 'string', 'format' => 'uuid'],
          ),
          new Parameter(
            name: 'page',
            in: 'query',
            required: false,
            description: 'Page number (1-based).',
            schema: ['type' => 'integer', 'default' => 1],
          ),
          new Parameter(
            name: 'itemsPerPage',
            in: 'query',
            required: false,
            description: 'Number of notifications per page.',
            schema: ['type' => 'integer', 'default' => 20],
          ),
        ],
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Notifications retrieved successfully'),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(description: 'Authentication required'),
        ],
      ),
    ),
    new Get(
      name: NotificationOperations::MERCURE_SUBSCRIPTION,
      uriTemplate: '/subscription',
      input: false,
      output: MercureSubscriptionOutput::class,
      provider: GetMercureSubscriptionProvider::class,
      normalizationContext: ['groups' => [NotificationSerializationGroup::MERCURE_SUBSCRIPTION]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Notification'],
        summary: 'Get Mercure subscription token',
        description: 'Returns a Mercure subscriber JWT and the private SSE topic for the authenticated user. The client must pass this token in the Authorization header (Bearer) when opening an EventSource connection to the Mercure hub.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Mercure subscription token returned successfully'),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(description: 'Authentication required'),
        ],
      ),
    ),
    // NOTE: /unread-count and /read-all must be declared before the /{id}
    // routes below, otherwise they are swallowed by the {id} placeholder.
    new Get(
      name: NotificationOperations::UNREAD_COUNT,
      uriTemplate: '/unread-count',
      input: false,
      output: UnreadNotificationsCountOutput::class,
      provider: GetUnreadNotificationsCountProvider::class,
      normalizationContext: ['groups' => [NotificationSerializationGroup::UNREAD_COUNT]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Notification'],
        summary: 'Get unread notifications count',
        description: 'Returns the unread notification count for the authenticated user.',
        security: [['bearerAuth' => []]],
        parameters: [
          new Parameter(
            name: 'organization',
            in: 'query',
            required: false,
            description: 'Filter by organization identifier. When omitted, unread notifications across all organizations (and account-level ones) are counted.',
            schema: ['type' => 'string', 'format' => 'uuid'],
          ),
        ],
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Unread notification count returned successfully'),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(description: 'Authentication required'),
        ],
      ),
    ),
    new Patch(
      name: NotificationOperations::MARK_ALL_AS_READ,
      uriTemplate: '/read-all',
      input: false,
      output: MarkAllNotificationsAsReadOutput::class,
      processor: MarkAllNotificationsAsReadProcessor::class,
      normalizationContext: ['groups' => [NotificationSerializationGroup::MARK_ALL_AS_READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Notification'],
        summary: 'Mark all notifications as read',
        description: 'Marks every unread notification of the authenticated user as read. Idempotent: calling it again once everything is read affects zero notifications.',
        security: [['bearerAuth' => []]],
        parameters: [
          new Parameter(
            name: 'organization',
            in: 'query',
            required: false,
            description: 'Filter by organization identifier. When omitted, notifications across all organizations (and account-level ones) are marked as read.',
            schema: ['type' => 'string', 'format' => 'uuid'],
          ),
        ],
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Notifications marked as read'),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(description: 'Authentication required'),
        ],
      ),
    ),
    // NOTE: /preferences must be declared before the /{id} routes below,
    // otherwise it would be swallowed by the {id} placeholder.
    new Get(
      name: NotificationOperations::GET_PREFERENCES,
      uriTemplate: '/preferences',
      input: false,
      output: NotificationPreferencesOutput::class,
      provider: GetNotificationPreferencesProvider::class,
      normalizationContext: ['groups' => [NotificationSerializationGroup::PREFERENCES]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Notification'],
        summary: 'Get notification preferences',
        description: 'Returns the authenticated user\'s customized per-category delivery preferences. A category with no entry is enabled on every channel (email and Mercure); only explicit customizations are stored.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Notification preferences retrieved successfully'),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(description: 'Authentication required'),
        ],
      ),
    ),
    new Patch(
      name: NotificationOperations::UPDATE_PREFERENCES,
      uriTemplate: '/preferences',
      input: UpdateNotificationPreferencesInput::class,
      output: NotificationPreferencesOutput::class,
      processor: UpdateNotificationPreferencesProcessor::class,
      normalizationContext: ['groups' => [NotificationSerializationGroup::PREFERENCES]],
      denormalizationContext: ['groups' => [NotificationSerializationGroup::PREFERENCES_WRITE]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Notification'],
        summary: 'Update notification preferences',
        description: 'Upserts one or more per-category delivery preferences for the authenticated user and returns the full customized set. This suppresses email/Mercure delivery only — matching notifications are still persisted and remain visible in the in-app list.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Notification preferences updated successfully'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Invalid request - validation failed'),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(description: 'Authentication required'),
        ],
      ),
    ),
    new Get(
      name: NotificationOperations::GET,
      uriTemplate: '/{id}',
      input: false,
      output: NotificationOutput::class,
      provider: GetNotificationProvider::class,
      normalizationContext: ['groups' => [NotificationSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Notification'],
        summary: 'Get notification',
        description: 'Returns a notification owned by the authenticated user.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Notification retrieved successfully'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Notification not found'),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(description: 'Authentication required'),
        ],
      ),
    ),
    new Patch(
      name: NotificationOperations::MARK_AS_READ,
      uriTemplate: '/{id}/read',
      input: false,
      output: NotificationOutput::class,
      provider: GetNotificationProvider::class,
      processor: MarkNotificationAsReadProcessor::class,
      normalizationContext: ['groups' => [NotificationSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Notification'],
        summary: 'Mark notification as read',
        description: 'Marks one notification as read for the authenticated user.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Notification marked as read'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Notification not found'),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(description: 'Authentication required'),
        ],
      ),
    ),
  ],
)]
final class NotificationResource
{
}
