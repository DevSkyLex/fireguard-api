<?php

declare(strict_types=1);

namespace Notification\Presentation\Api\Dto\Output\MercureSubscription;

use ApiPlatform\Metadata\ApiProperty;
use Notification\Presentation\Api\Serialization\NotificationSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO MercureSubscriptionOutput.
 *
 * Carries the Mercure subscriber JWT and the topic the client
 * must subscribe to in order to receive private notification updates.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class MercureSubscriptionOutput
{
  // #region Properties
  /**
   * Property token.
   *
   * Mercure subscriber JWT.
   *
   * @since 1.0.0
   */
  #[Groups([NotificationSerializationGroup::MERCURE_SUBSCRIPTION])]
  #[ApiProperty(readable: true, writable: false)]
  public string $token = '';

  /**
   * Property topic.
   *
   * Mercure topic to subscribe to (e.g. /users/{id}/notifications).
   *
   * @since 1.0.0
   */
  #[Groups([NotificationSerializationGroup::MERCURE_SUBSCRIPTION])]
  #[ApiProperty(readable: true, writable: false)]
  public string $topic = '';
  // #endregion
}
