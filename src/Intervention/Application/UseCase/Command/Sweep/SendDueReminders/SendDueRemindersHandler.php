<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Command\Sweep\SendDueReminders;

use DateTimeImmutable;
use Intervention\Application\Contract\Reminder\InterventionReminderCandidate;
use Intervention\Application\Port\Outbound\InterventionReminderPort;
use Intervention\Application\Service\InterventionNotificationService;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\ClockPort;

use function array_unique;
use function array_values;
use function count;
use function sprintf;

/**
 * UseCase SendDueRemindersHandler.
 *
 * Idempotent, safe-to-re-run due-date reminder sweep: pages through every
 * intervention whose `dueAt` is within the due-soon window or already past,
 * restricted to statuses where field work is still expected (`planned`,
 * `in_progress`, `changes_requested`), and notifies the responsible member
 * and participants through {@see InterventionNotificationService}.
 *
 * Anti-spam: a candidate is only selected while its `due_soon_notified_at` /
 * `overdue_notified_at` stamp is `null` ({@see InterventionReminderPort}),
 * and the stamp is set immediately after the notification is sent — so a
 * repeat tick never re-announces the same threshold for the same `dueAt`. A
 * reschedule clears both stamps at the source
 * (`DoctrineInterventionWorkflowGatewayAdapter::updateIntervention`), which
 * is what lets a later reminder fire again for the new date.
 *
 * Every page is processed independently to keep memory bounded regardless of
 * how many organizations/interventions exist.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SendDueRemindersHandler implements CommandHandler
{
  // #region Constants
  /**
   * Page size used for the sweep, keeping every batch bounded in memory.
   */
  private const int PAGE_SIZE = 200;

  /**
   * The due-soon window, in hours: a `dueAt` within this many hours of now
   * qualifies for the `intervention.due_soon` reminder.
   */
  private const int DUE_SOON_WINDOW_HOURS = 48;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param InterventionReminderPort $reminders the reminder candidates port
   * @param InterventionNotificationService $notifications the notification service
   * @param ClockPort $clock the clock port
   */
  public function __construct(
    private InterventionReminderPort $reminders,
    private InterventionNotificationService $notifications,
    private ClockPort $clock,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param SendDueRemindersCommand $command the command value
   *
   * @return SendDueRemindersResult the command result
   */
  public function __invoke(SendDueRemindersCommand $command): SendDueRemindersResult
  {
    $now = $this->clock->now();

    return new SendDueRemindersResult(
      dueSoonCount: $this->sweepDueSoon($now),
      overdueCount: $this->sweepOverdue($now),
    );
  }

  /**
   * Method sweepDueSoon.
   *
   * @since 1.0.0
   *
   * @param DateTimeImmutable $now the current instant
   *
   * @return int the number of due-soon reminders sent
   */
  private function sweepDueSoon(DateTimeImmutable $now): int
  {
    $threshold = $now->modify(sprintf('+%d hours', self::DUE_SOON_WINDOW_HOURS));
    $count = 0;
    $offset = 0;

    do {
      $page = $this->reminders->pageDueSoon($now, $threshold, self::PAGE_SIZE, $offset);

      foreach ($page->items as $candidate) {
        $this->notifications->dueSoon(
          $candidate->id,
          $candidate->number,
          $candidate->name,
          $candidate->organizationId,
          $candidate->dueAt,
          $this->recipientMemberIds($candidate),
        );
        $this->reminders->markDueSoonNotified($candidate->id, $now);
        ++$count;
      }

      $offset += self::PAGE_SIZE;
    } while (self::PAGE_SIZE === count($page->items));

    return $count;
  }

  /**
   * Method sweepOverdue.
   *
   * @since 1.0.0
   *
   * @param DateTimeImmutable $now the current instant
   *
   * @return int the number of overdue reminders sent
   */
  private function sweepOverdue(DateTimeImmutable $now): int
  {
    $count = 0;
    $offset = 0;

    do {
      $page = $this->reminders->pageOverdue($now, self::PAGE_SIZE, $offset);

      foreach ($page->items as $candidate) {
        $this->notifications->overdue(
          $candidate->id,
          $candidate->number,
          $candidate->name,
          $candidate->organizationId,
          $candidate->dueAt,
          $this->recipientMemberIds($candidate),
        );
        $this->reminders->markOverdueNotified($candidate->id, $now);
        ++$count;
      }

      $offset += self::PAGE_SIZE;
    } while (self::PAGE_SIZE === count($page->items));

    return $count;
  }

  /**
   * Method recipientMemberIds.
   *
   * The responsible member and every participant, deduplicated. Active/
   * organization-membership validation happens downstream in
   * {@see InterventionNotificationService}, mirroring how `mentioned()`
   * re-validates a member id sourced outside the mutation that owns it.
   *
   * @since 1.0.0
   *
   * @param InterventionReminderCandidate $candidate the reminder candidate value
   *
   * @return list<string> the candidate recipient member ids
   */
  private function recipientMemberIds(InterventionReminderCandidate $candidate): array
  {
    $memberIds = $candidate->participants;
    if (null !== $candidate->responsibleId) {
      $memberIds[] = $candidate->responsibleId;
    }

    return array_values(array_unique($memberIds));
  }
  // #endregion
}
