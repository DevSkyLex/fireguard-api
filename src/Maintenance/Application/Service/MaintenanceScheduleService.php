<?php

declare(strict_types=1);

namespace Maintenance\Application\Service;

use DateTimeImmutable;
use Maintenance\Application\Contract\Schedule\MaintenanceScheduleSnapshot;
use Maintenance\Application\Port\Inbound\MaintenanceSchedulePort;
use Maintenance\Application\Port\Outbound\Compliance\MaintenanceCompliancePolicyPort;
use Maintenance\Application\Port\Outbound\Directory\MaintenanceEquipmentDirectoryPort;
use Maintenance\Application\Port\Outbound\Schedule\MaintenanceScheduleRepositoryPort;
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
  ) {
  }
  // #endregion

  // #region Methods
  public function onInspectionClosed(string $organizationId, string $equipmentId, DateTimeImmutable $closedAt): void
  {
    $existing = $this->schedules->findByOrganizationAndEquipment($organizationId, $equipmentId);
    $facilityId = $existing?->facilityId;
    $equipmentType = $existing?->equipmentType;

    if (null === $existing) {
      $equipment = $this->directory->findEquipment($equipmentId);
      if (null === $equipment || $equipment->organizationId !== $organizationId) {
        // Best-effort: nothing to synchronize (equipment unknown or belongs
        // to a different organization than the closing inspection claims).
        return;
      }

      $facilityId = $equipment->facilityId;
      $equipmentType = $equipment->equipmentType;
    }

    /** @var string $equipmentType always resolved above, either from the existing schedule or the directory */
    $compliancePolicy = $this->compliancePolicy->compliancePolicy($organizationId);
    $effectiveInterval = $this->policy->resolveEffectiveInterval(
      $existing?->intervalOverride,
      $compliancePolicy->periodicityFor($equipmentType),
    );
    $nextDueAt = $this->policy->computeNextDueAt($closedAt, $effectiveInterval);
    $dueStatus = $this->policy->computeDueStatus(
      $nextDueAt,
      $effectiveInterval,
      $this->clock->now(),
      $compliancePolicy->reminderWindowDays,
    );
    $resetRemindedFor = $this->policy->shouldResetRemindedFor($existing?->nextDueAt, $nextDueAt);

    $this->schedules->save(new MaintenanceScheduleSnapshot(
      id: $existing?->id,
      organizationId: $organizationId,
      equipmentId: $equipmentId,
      facilityId: $facilityId,
      equipmentType: $equipmentType,
      intervalOverride: $existing?->intervalOverride,
      lastInspectionClosedAt: $closedAt,
      nextDueAt: $nextDueAt,
      dueStatus: $dueStatus->value,
      lastRemindedAt: $existing?->lastRemindedAt,
      remindedFor: $resetRemindedFor ? null : $existing?->remindedFor,
    ));
  }
  // #endregion
}
