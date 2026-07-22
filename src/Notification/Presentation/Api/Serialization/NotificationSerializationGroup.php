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

  /**
   * Group for unread notification count views.
   *
   * @since 1.1.0
   */
  public const string UNREAD_COUNT = 'notification:unread_count';

  /**
   * Group for mark-all-as-read result views.
   *
   * @since 1.1.0
   */
  public const string MARK_ALL_AS_READ = 'notification:mark_all_as_read';

  /**
   * Group for notification preference read views.
   *
   * @since 1.2.0
   */
  public const string PREFERENCES = 'notification:preferences:read';

  /**
   * Group for notification preference write payloads.
   *
   * @since 1.2.0
   */
  public const string PREFERENCES_WRITE = 'notification:preferences:write';

  /**
   * Group for unified inbox feed views.
   *
   * @since 1.3.0
   */
  public const string INBOX_READ = 'notification:inbox:read';

  /**
   * Group for unified inbox unread-count views.
   *
   * @since 1.4.0
   */
  public const string INBOX_UNREAD_COUNT = 'notification:inbox:unread_count';
}
