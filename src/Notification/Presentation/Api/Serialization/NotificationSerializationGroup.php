<?php

declare(strict_types=1);

namespace Notification\Presentation\Api\Serialization;

/**
 * Serialization groups for notification API.
 *
 * @category Serialization
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class NotificationSerializationGroup
{
  /**
   * Group for read notification views.
   *
   * @since 1.0.0
   */
  public const string READ = 'notification:read';

  /**
   * Group for Mercure subscription views.
   *
   * @since 1.0.0
   */
  public const string MERCURE_SUBSCRIPTION = 'notification:mercure_subscription';

  /**
   * Group for notification type list views.
   *
   * @since 1.0.0
   */
  public const string NOTIFICATION_TYPE_READ = 'notification_type:read';
}
