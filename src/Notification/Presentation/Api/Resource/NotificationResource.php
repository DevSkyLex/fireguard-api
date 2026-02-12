<?php

declare(strict_types=1);

namespace Notification\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get, GetCollection, Patch};
use ApiPlatform\OpenApi\Model\{Operation, Parameter, Response};
use Notification\Presentation\Api\Dto\Output\Notification\NotificationOutput;
use Notification\Presentation\Api\Operation\NotificationOperations;
use Notification\Presentation\Api\Processor\Notification\MarkNotificationAsReadProcessor;
use Notification\Presentation\Api\Provider\Notification\{GetNotificationProvider, ListNotificationsProvider};
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
      normalizationContext: ['groups' => [NotificationSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Notification'],
        summary: 'List notifications',
        description: 'Returns notifications for the authenticated user.',
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
            name: 'limit',
            in: 'query',
            required: false,
            description: 'Maximum number of notifications (1-100).',
            schema: ['type' => 'integer'],
          ),
        ],
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Notifications retrieved successfully'),
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
