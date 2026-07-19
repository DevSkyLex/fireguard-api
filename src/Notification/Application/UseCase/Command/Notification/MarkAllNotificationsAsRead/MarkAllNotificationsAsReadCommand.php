<?php

declare(strict_types=1);

namespace Notification\Application\UseCase\Command\Notification\MarkAllNotificationsAsRead;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase MarkAllNotificationsAsReadCommand.
 *
 * @category UseCase
 *
 * @version 1.1.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MarkAllNotificationsAsReadCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.1.0
   *
   * @param string $userId the user identifier
   * @param string|null $organizationId exact organization filter; when omitted, notifications across all organizations (and account-level ones) are marked as read
   */
  public function __construct(
    public string $userId,
    public ?string $organizationId = null,
  ) {
  }
  // #endregion
}
