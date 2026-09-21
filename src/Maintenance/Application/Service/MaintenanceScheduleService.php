<?php

declare(strict_types=1);

namespace Maintenance\Application\Service;

use DateTimeImmutable;
use Maintenance\Application\Contract\Schedule\MaintenanceScheduleSnapshot;
use Maintenance\Application\Port\Inbound\MaintenanceSchedulePort;
use Maintenance\Application\Port\Outbound\Compliance\MaintenanceCompliancePolicyPort;
use Maintenance\Application\Port\Outbound\Directory\MaintenanceEquipmentDirectoryPort;
use Maintenance\Application\Port\Outbound\Schedule\{MaintenanceScheduleLockPort, MaintenanceScheduleRepositoryPort};
use Maintenance\Domain\Service\MaintenanceScheduleRecomputePolicy;
use Shared\Application\Port\Outbound\ClockPort;

/**
 * Service MaintenanceScheduleService.
 *
 * Implements the inbound synchronization port: recomputes one equipment's
 * maintenance schedule after an inspection closes.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MaintenanceScheduleService implements MaintenanceSchedulePort
{
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
    private MaintenanceScheduleLockPort $locks,
    private \Maintenance\Application\Port\Outbound\Schedule\MaintenanceInspectionHistoryPort $inspectionHistory,
  ) {
  }
  // #endregion

  // #region Methods
  public function onInspectionClosed(string $organizationId, string $equipmentId, DateTimeImmutable $closedAt): void
  {
    $this->locks->synchronized($organizationId, $equipmentId, fn () => $this->recompute($organizationId, $equipmentId, $closedAt));
  }

  /**
   * Reload current source state under the same lock used for inspection and override updates.
   */
  public function refreshEquipment(string $organizationId, string $equipmentId): void
  {
    $this->locks->synchronized($organizationId, $equipmentId, fn () => $this->recompute($organizationId, $equipmentId));
  }

  private function recompute(string $organizationId, string $equipmentId, ?DateTimeImmutable $closedAt = null): void
  {
    $equipment = $this->directory->findEquipment($equipmentId);
    if (null === $equipment || $equipment->organizationId !== $organizationId || 'decommissioned' === $equipment->status) {
      $this->schedules->removeByOrganizationAndEquipment($organizationId, $equipmentId);

      return;
    }
    $existing = $this->schedules->findByOrganizationAndEquipment($organizationId, $equipmentId);
    // A delayed inspection closure must never move the last inspection backwards.
    $lastClosedAt = $existing?->lastInspectionClosedAt;
    $publishedClosedAt = $this->inspectionHistory->latestClosedAt($organizationId, $equipmentId);
    if (null !== $publishedClosedAt && (null === $lastClosedAt || $publishedClosedAt > $lastClosedAt)) {
      $lastClosedAt = $publishedClosedAt;
    }
    if (null !== $closedAt && (null === $lastClosedAt || $closedAt > $lastClosedAt)) {
      $lastClosedAt = $closedAt;
    }
    $compliancePolicy = $this->compliancePolicy->compliancePolicy($organizationId);
    $effectiveInterval = $this->policy->resolveEffectiveInterval($existing?->intervalOverride, $compliancePolicy->periodicityFor($equipment->equipmentType));
    $nextDueAt = $this->policy->computeNextDueAt($lastClosedAt, $effectiveInterval);
    $now = $this->clock->now();
    $dueStatus = $this->policy->computeDueStatus($nextDueAt, $effectiveInterval, $now, $compliancePolicy->reminderWindowDays);
    $resetRemindedFor = $this->policy->shouldResetRemindedFor($existing?->nextDueAt, $nextDueAt);
    $this->schedules->save(new MaintenanceScheduleSnapshot(
      id: $existing?->id,
      organizationId: $organizationId,
      equipmentId: $equipmentId,
      facilityId: $equipment->facilityId,
      equipmentType: $equipment->equipmentType,
      intervalOverride: $existing?->intervalOverride,
      lastInspectionClosedAt: $lastClosedAt,
      nextDueAt: $nextDueAt,
      dueStatus: $dueStatus->value,
      lastRemindedAt: $existing?->lastRemindedAt,
      remindedFor: $resetRemindedFor ? null : $existing?->remindedFor,
      evaluatedAt: $now,
    ));
  }
  // #endregion
}
