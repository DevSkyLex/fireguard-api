<?php

declare(strict_types=1);

namespace Maintenance\Application\UseCase\Command\Sweep\RecomputeMaintenanceSchedules;

use DateTimeImmutable;
use Maintenance\Application\Contract\Directory\TrackableEquipment;
use Maintenance\Application\Contract\Schedule\{MaintenanceScheduleSnapshot, MaintenanceScheduleView};
use Maintenance\Application\Port\Outbound\Compliance\MaintenanceCompliancePolicyPort;
use Maintenance\Application\Port\Outbound\Directory\MaintenanceEquipmentDirectoryPort;
use Maintenance\Application\Port\Outbound\Schedule\MaintenanceScheduleRepositoryPort;
use Maintenance\Application\Service\MaintenanceReminderNotifier;
use Maintenance\Domain\Service\MaintenanceScheduleRecomputePolicy;
use Maintenance\Domain\ValueObject\MaintenanceDueStatus;
use Shared\Application\Message\{CommandHandler, VoidResult};
use Shared\Application\Port\Outbound\ClockPort;

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
 * 3. Sends a due-soon/overdue reminder exactly once per distinct
 *    `nextDueAt` (the `remindedFor` anti-duplicate marker), respecting the
 *    organization's notification policy.
 *
 * Every step is processed page-wise to keep memory bounded regardless of
 * how many organizations/equipment exist.
 *
 * @category UseCase
 *
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
   * @param MaintenanceReminderNotifier $notifier the reminder notifier
   * @param ClockPort $clock the clock port
   */
  public function __construct(
    private MaintenanceScheduleRepositoryPort $schedules,
    private MaintenanceEquipmentDirectoryPort $directory,
    private MaintenanceCompliancePolicyPort $compliancePolicy,
    private MaintenanceScheduleRecomputePolicy $policy,
    private MaintenanceReminderNotifier $notifier,
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

      foreach ($page as $equipment) {
        $this->reconcileOneEquipment($equipment);
      }

      $offset += self::PAGE_SIZE;
    } while (self::PAGE_SIZE === count($page));
  }

  /**
   * Method reconcileOneEquipment.
   *
   * @since 1.0.0
   *
   * @param TrackableEquipment $equipment the directory equipment view
   */
  private function reconcileOneEquipment(TrackableEquipment $equipment): void
  {
    if ('decommissioned' === $equipment->status) {
      $this->schedules->removeByOrganizationAndEquipment($equipment->organizationId, $equipment->equipmentId);

      return;
    }

    $existing = $this->schedules->findByOrganizationAndEquipment($equipment->organizationId, $equipment->equipmentId);
    if (null !== $existing) {
      // Already reconciled: due status is refreshed by the recompute pass.
      return;
    }

    $compliancePolicy = $this->compliancePolicy->compliancePolicy($equipment->organizationId);
    $effectiveInterval = $this->policy->resolveEffectiveInterval(null, $compliancePolicy->periodicityFor($equipment->equipmentType));
    $dueStatus = $this->policy->computeDueStatus(null, $effectiveInterval, $this->clock->now(), $compliancePolicy->reminderWindowDays);

    $this->schedules->save(new MaintenanceScheduleSnapshot(
      id: null,
      organizationId: $equipment->organizationId,
      equipmentId: $equipment->equipmentId,
      facilityId: $equipment->facilityId,
      equipmentType: $equipment->equipmentType,
      intervalOverride: null,
      lastInspectionClosedAt: null,
      nextDueAt: null,
      dueStatus: $dueStatus->value,
    ));
  }

  /**
   * Method recomputeAndRemind.
   *
   * Recomputes due status for every schedule and sends reminders where due,
   * paging through the whole table.
   *
   * @since 1.0.0
   */
  private function recomputeAndRemind(): void
  {
    $offset = 0;

    do {
      $page = $this->schedules->pageForSweep(self::PAGE_SIZE, $offset);

      foreach ($page->items as $schedule) {
        $this->recomputeAndRemindOne($schedule);
      }

      $offset += self::PAGE_SIZE;
    } while (self::PAGE_SIZE === count($page->items));
  }

  /**
   * Method recomputeAndRemindOne.
   *
   * @since 1.0.0
   *
   * @param MaintenanceScheduleView $schedule the schedule view
   */
  private function recomputeAndRemindOne(MaintenanceScheduleView $schedule): void
  {
    $compliancePolicy = $this->compliancePolicy->compliancePolicy($schedule->organizationId);
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
      return;
    }

    $lastRemindedAt = $schedule->lastRemindedAt;
    $remindedFor = $schedule->remindedFor;

    if ($needsReminder) {
      /** @var DateTimeImmutable $nextDueAt guarded by $needsReminder above */
      $nextDueAt = $schedule->nextDueAt;
      $this->notifier->remind(
        $schedule->organizationId,
        $schedule->equipmentId,
        $schedule->facilityId,
        $nextDueAt,
        MaintenanceDueStatus::OVERDUE === $dueStatus,
      );
      $lastRemindedAt = $this->clock->now();
      $remindedFor = $nextDueAt;
    }

    $this->schedules->save(new MaintenanceScheduleSnapshot(
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
    ));
  }
  // #endregion
}
