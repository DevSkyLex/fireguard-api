<?php

declare(strict_types=1);

namespace Notification\Application\UseCase\Command\Notification\MarkAllNotificationsAsRead;

use Notification\Application\Port\Outbound\NotificationRepositoryPort;
use Shared\Application\Message\CommandHandler;

/**
 * UseCase MarkAllNotificationsAsReadHandler.
 *
 * @category UseCase
 *
 * @version 1.1.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MarkAllNotificationsAsReadHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.1.0
   *
   * @param NotificationRepositoryPort $notificationRepository the notification repository port
   */
  public function __construct(
    private NotificationRepositoryPort $notificationRepository,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Marks every unread notification of a user as read, optionally scoped to
   * one organization, through a single bulk update. Idempotent: a second
   * call once every notification is already read affects zero rows.
   *
   * @since 1.1.0
   *
   * @param MarkAllNotificationsAsReadCommand $command the command payload
   *
   * @return MarkAllNotificationsAsReadResult the use case result
   */
  public function __invoke(MarkAllNotificationsAsReadCommand $command): MarkAllNotificationsAsReadResult
  {
    $count = $this->notificationRepository->markAllAsReadForUser(
      userId: $command->userId,
      organizationId: $command->organizationId,
    );

    return new MarkAllNotificationsAsReadResult(count: $count);
  }
  // #endregion
}
