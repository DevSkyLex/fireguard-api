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

  /**
   * Get Mercure subscription token operation.
   *
   * @since 1.0.0
   */
  public const string MERCURE_SUBSCRIPTION = 'notification_mercure_subscription';

  /**
   * List notification types operation.
   *
   * @since 1.0.0
   */
  public const string LIST_TYPES = 'notification_types_list';

  /**
   * Get unread notifications count operation.
   *
   * @since 1.1.0
   */
  public const string UNREAD_COUNT = 'notification_unread_count';

  /**
   * Mark all notifications as read operation.
   *
   * @since 1.1.0
   */
  public const string MARK_ALL_AS_READ = 'notification_mark_all_as_read';

  /**
   * Get the authenticated user's notification preferences operation.
   *
   * @since 1.2.0
   */
  public const string GET_PREFERENCES = 'notification_preferences_get';

  /**
   * Update the authenticated user's notification preferences operation.
   *
   * @since 1.2.0
   */
  public const string UPDATE_PREFERENCES = 'notification_preferences_update';

  /**
   * Get the authenticated user's unified inbox feed operation.
   *
   * @since 1.3.0
   */
  public const string GET_INBOX = 'inbox_get';

  /**
   * Get the authenticated user's unified inbox unread count operation.
   *
   * @since 1.4.0
   */
  public const string GET_INBOX_UNREAD_COUNT = 'inbox_unread_count';
}
