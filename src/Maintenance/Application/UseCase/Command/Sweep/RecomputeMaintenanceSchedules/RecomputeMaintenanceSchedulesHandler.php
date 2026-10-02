<?php

declare(strict_types=1);

namespace Maintenance\Application\UseCase\Command\Sweep\RecomputeMaintenanceSchedules;

use DateTimeImmutable;
use Maintenance\Application\Contract\Compliance\MaintenanceCompliancePolicy;
use Maintenance\Application\Contract\Schedule\{MaintenanceScheduleSnapshot, MaintenanceScheduleView};
use Maintenance\Application\Port\Outbound\Compliance\MaintenanceCompliancePolicyPort;
use Maintenance\Application\Port\Outbound\Directory\MaintenanceEquipmentDirectoryPort;
use Maintenance\Application\Port\Outbound\Schedule\MaintenanceScheduleRepositoryPort;
use Maintenance\Domain\Event\Reminder\MaintenanceReminderRequestedEvent;
use Maintenance\Domain\Service\MaintenanceScheduleRecomputePolicy;
use Maintenance\Domain\ValueObject\MaintenanceDueStatus;
use Shared\Application\Message\{CommandHandler, VoidResult};
use Shared\Application\Port\Outbound\{ClockPort, EventDispatcherPort};

use function array_keys;
use function array_map;
use function count;
use function in_array;

/**
 * UseCase RecomputeMaintenanceSchedulesHandler.
 *
 * Idempotent, safe-to-re-run recurring sweep:
 *
 * 1. Reconciles the schedule table against the trackable equipment
 *    directory across every organization: missing schedules are bootstrapped
 *    (never-inspected equipment: `nextDueAt` null, `dueStatus` derived from
 *    periodicity presence), and decommissioned equipment's schedule is
 *    dropped.
 * 2. Recomputes `dueStatus` for every schedule against the current instant.
 * 3. Queues a due-soon/overdue reminder per distinct `nextDueAt` in the same
 *    transaction as its `remindedFor` marker. Delivery consumers retain
 *    per-recipient receipts and retry incomplete channels.
 *
 * Every step is processed page-wise to keep memory bounded regardless of
 * how many organizations/equipment exist.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RecomputeMaintenanceSchedulesHandler implements CommandHandler
{
  // #region Constants
  /**
   * Page size used for both the equipment directory and the schedule sweep,
   * keeping every batch bounded in memory.
   */
  private const int PAGE_SIZE = 200;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param MaintenanceScheduleRepositoryPort $schedules the schedule repository port
   * @param MaintenanceEquipmentDirectoryPort $directory the cross-module equipment directory port
   * @param MaintenanceCompliancePolicyPort $compliancePolicy the cross-module organization compliance policy port
   * @param MaintenanceScheduleRecomputePolicy $policy the pure recompute policy
   * @param ClockPort $clock the clock port
   */
  public function __construct(
    private MaintenanceScheduleRepositoryPort $schedules,
    private MaintenanceEquipmentDirectoryPort $directory,
    private MaintenanceCompliancePolicyPort $compliancePolicy,
    private MaintenanceScheduleRecomputePolicy $policy,
    private ClockPort $clock,
    private \Maintenance\Application\Service\MaintenanceScheduleService $synchronizer,
    private \Maintenance\Application\Port\Outbound\Schedule\MaintenanceScheduleLockPort $locks,
    private EventDispatcherPort $reminders,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param RecomputeMaintenanceSchedulesCommand $command the command value
   *
   * @return VoidResult the command result
   */
  public function __invoke(RecomputeMaintenanceSchedulesCommand $command): VoidResult
  {
    $this->reconcileTrackableEquipment();
    $this->recomputeAndRemind();

    return new VoidResult();
  }

  /**
   * Method reconcileTrackableEquipment.
   *
   * Bootstraps missing schedules and drops decommissioned equipment's
   * schedules, paging through every organization's equipment.
   *
   * @since 1.0.0
   */
  private function reconcileTrackableEquipment(): void
  {
    $offset = 0;

    do {
      $page = $this->directory->listEquipmentPage(self::PAGE_SIZE, $offset);

      $this->synchronizer->refreshPage($page);

      $offset += self::PAGE_SIZE;
    } while (self::PAGE_SIZE === count($page));
  }

  /**
   * Method recomputeAndRemind.
   *
   * Recomputes due status for every schedule and queues reminders where due,
   * paging through the whole table.
   *
   * @since 1.0.0
   */
  private function recomputeAndRemind(): void
  {
    $offset = 0;

    do {
      $page = $this->schedules->pageForSweep(self::PAGE_SIZE, $offset);

      $this->recomputeAndRemindPage($page->items);

      $offset += self::PAGE_SIZE;
    } while (self::PAGE_SIZE === count($page->items));
  }

  /**
   * Method recomputeAndRemindPage
   *
   * Acquires every page scope before reloading schedules and enqueueing reminder updates.
   *
   * @access private
   *
   * @param list<MaintenanceScheduleView> $items the schedule identities to lock
   *
   * @return void
   */
  private function recomputeAndRemindPage(array $items): void
  {
    $scopes = array_map(static fn (MaintenanceScheduleView $schedule): array => ['organizationId' => $schedule->organizationId, 'equipmentId' => $schedule->equipmentId], $items);
    if ([] === $scopes) {
      return;
    }

    $this->locks->synchronizedBatch($scopes, fn () => $this->recomputeLockedSchedules($items));
  }

  /**
   * Method recomputeLockedSchedules
   *
   * Recomputes fresh schedules in batches while their reminders and markers share the page transaction.
   *
   * @access private
   *
   * @param list<MaintenanceScheduleView> $items the schedule identities within the locked page
   *
   * @return void
   */
  private function recomputeLockedSchedules(array $items): void
  {
    $groups = [];
    foreach ($items as $schedule) {
      $groups[$schedule->organizationId][] = $schedule->equipmentId;
    }
    $policies = $this->compliancePolicy->compliancePolicies(array_keys($groups));
    $updates = [];
    foreach ($groups as $organizationId => $equipmentIds) {
      $current = $this->schedules->findForEquipment($organizationId, $equipmentIds);
      $compliance = $policies[$organizationId] ?? $this->compliancePolicy->compliancePolicy($organizationId);
      foreach ($current as $schedule) {
        $snapshot = $this->recomputeAndRemindOne($schedule, $compliance);
        if (null !== $snapshot) {
          $updates[] = $snapshot;
        }
      }
    }
    $this->schedules->saveBatch($updates);
  }

  /**
   * Method recomputeAndRemindOne.
   *
   * @since 1.0.0
   *
   * @param MaintenanceScheduleView $schedule the schedule view
   */
  private function recomputeAndRemindOne(MaintenanceScheduleView $schedule, MaintenanceCompliancePolicy $compliancePolicy): ?MaintenanceScheduleSnapshot
  {
    $effectiveInterval = $this->policy->resolveEffectiveInterval(
      $schedule->intervalOverride,
      $compliancePolicy->periodicityFor($schedule->equipmentType),
    );
    $dueStatus = $this->policy->computeDueStatus(
      $schedule->nextDueAt,
      $effectiveInterval,
      $this->clock->now(),
      $compliancePolicy->reminderWindowDays,
    );

    $isDueOrOverdue = in_array($dueStatus, [MaintenanceDueStatus::DUE_SOON, MaintenanceDueStatus::OVERDUE], true);
    $alreadyRemindedForThisDueDate = null !== $schedule->remindedFor
      && null !== $schedule->nextDueAt
      && $schedule->remindedFor->getTimestamp() === $schedule->nextDueAt->getTimestamp();
    $needsReminder = $isDueOrOverdue && null !== $schedule->nextDueAt && !$alreadyRemindedForThisDueDate;

    if ($dueStatus->value === $schedule->dueStatus && !$needsReminder) {
      // Nothing changed: skip the write entirely.
      return null;
    }

    $lastRemindedAt = $schedule->lastRemindedAt;
    $remindedFor = $schedule->remindedFor;

    if ($needsReminder) {
      /** @var DateTimeImmutable $nextDueAt guarded by $needsReminder above */
      $nextDueAt = $schedule->nextDueAt;
      $this->reminders->dispatch(new MaintenanceReminderRequestedEvent(
        $schedule->organizationId,
        $schedule->equipmentId,
        $schedule->facilityId,
        $nextDueAt,
        MaintenanceDueStatus::OVERDUE === $dueStatus,
      ));
      $lastRemindedAt = $this->clock->now();
      $remindedFor = $nextDueAt;
    }

    return new MaintenanceScheduleSnapshot(
      id: $schedule->id,
      organizationId: $schedule->organizationId,
      equipmentId: $schedule->equipmentId,
      facilityId: $schedule->facilityId,
      equipmentType: $schedule->equipmentType,
      intervalOverride: $schedule->intervalOverride,
      lastInspectionClosedAt: $schedule->lastInspectionClosedAt,
      nextDueAt: $schedule->nextDueAt,
      dueStatus: $dueStatus->value,
      lastRemindedAt: $lastRemindedAt,
      remindedFor: $remindedFor,
      evaluatedAt: $this->clock->now(),
    );
  }
  // #endregion
}
