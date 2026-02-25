<?php

declare(strict_types=1);

namespace Notification\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, GetCollection};
use ApiPlatform\OpenApi\Model\{Operation, Response};
use Notification\Presentation\Api\Dto\Output\NotificationType\NotificationTypeOutput;
use Notification\Presentation\Api\Operation\NotificationOperations;
use Notification\Presentation\Api\Provider\NotificationType\ListNotificationTypesProvider;
use Notification\Presentation\Api\Serialization\NotificationSerializationGroup;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Resource NotificationTypeResource.
 *
 * Exposes the registry of known notification types so that clients
 * can build type selectors without hard-coding the list.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'NotificationType',
  routePrefix: '/notification-types',
  description: 'Registry of known notification types.',
  operations: [
    new GetCollection(
      name: NotificationOperations::LIST_TYPES,
      uriTemplate: '',
      input: false,
      output: NotificationTypeOutput::class,
      provider: ListNotificationTypesProvider::class,
      normalizationContext: ['groups' => [NotificationSerializationGroup::NOTIFICATION_TYPE_READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Notification'],
        summary: 'List notification types',
        description: 'Returns all known notification types with their categories. Useful for building type selector components on the frontend.',
        security: [['bearerAuth' => []]],
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Notification types retrieved successfully'),
          HttpResponse::HTTP_UNAUTHORIZED => new Response(description: 'Authentication required'),
        ],
      ),
    ),
  ],
)]
final class NotificationTypeResource
{
}
