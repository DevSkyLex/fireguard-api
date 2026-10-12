<?php

declare(strict_types=1);

namespace Notification\Infrastructure\Adapter\Idempotency;

use Notification\Application\UseCase\Command\Notification\MarkAllNotificationsAsRead\{MarkAllNotificationsAsReadCommand, MarkAllNotificationsAsReadResult};
use Notification\Application\UseCase\Command\Notification\MarkNotificationAsRead\{MarkNotificationAsReadCommand, MarkNotificationAsReadResult};
use Notification\Application\UseCase\Command\Notification\UpdateNotificationPreferences\{UpdateNotificationPreferencesCommand, UpdateNotificationPreferencesResult};
use Notification\Application\UseCase\Query\Notification\GetNotificationPreferences\NotificationPreferenceResult;
use Shared\Application\Contract\Http\HttpIdempotencyOperation;
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyOperationContributionPort;

/**
 * Class NotificationHttpIdempotencyOperationContributionAdapter
 *
 * Declares explicit owner transactions, result schemas and mandatory keys for every published HTTP mutation.
 *
 * @category Adapter
 */
final readonly class NotificationHttpIdempotencyOperationContributionAdapter implements HttpIdempotencyOperationContributionPort
{
  // #region Methods
  /**
   * {@inheritdoc}
   */
  public static function definitions(): array
  {
    return [
      new HttpIdempotencyOperation(
        name: 'notification_mark_as_read',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: MarkNotificationAsReadCommand::class,
        resultClass: MarkNotificationAsReadResult::class,
        requiredKey: true,
        resultIdentifierProperty: 'id',
        resourceKind: 'notification',
      ),
      new HttpIdempotencyOperation(
        name: 'notification_mark_all_as_read',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: MarkAllNotificationsAsReadCommand::class,
        resultClass: MarkAllNotificationsAsReadResult::class,
        requiredKey: true,
      ),
      new HttpIdempotencyOperation(
        name: 'notification_preferences_update',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: UpdateNotificationPreferencesCommand::class,
        resultClass: UpdateNotificationPreferencesResult::class,
        contractClasses: [NotificationPreferenceResult::class],
        requiredKey: true,
      ),
    ];
  }
  // #endregion
}
