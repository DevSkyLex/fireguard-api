<?php

declare(strict_types=1);

namespace Notification\Presentation\Api\Operation;

/**
 * Operation names for notification API.
 *
 * @category Operation
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class NotificationOperations
{
  /**
   * List notifications operation.
   *
   * @since 1.0.0
   */
  public const string LIST = 'notifications_list';

  /**
   * Get notification operation.
   *
   * @since 1.0.0
   */
  public const string GET = 'notification_get';

  /**
   * Mark notification as read operation.
   *
   * @since 1.0.0
   */
  public const string MARK_AS_READ = 'notification_mark_as_read';
}
