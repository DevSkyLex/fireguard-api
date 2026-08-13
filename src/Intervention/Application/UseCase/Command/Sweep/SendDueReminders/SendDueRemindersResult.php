<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Command\Sweep\SendDueReminders;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase SendDueRemindersResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SendDueRemindersResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param int $dueSoonCount the number of due-soon reminders sent
   * @param int $overdueCount the number of overdue reminders sent
   */
  public function __construct(
    public int $dueSoonCount,
    public int $overdueCount,
  ) {
  }
  // #endregion
}
