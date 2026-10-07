<?php

declare(strict_types=1);

namespace Maintenance\Application\Service;

use DateTimeImmutable;
use Maintenance\Application\Contract\Compliance\MaintenanceCompliancePolicy;
use Maintenance\Application\Contract\Directory\TrackableEquipment;
use Maintenance\Application\Contract\Schedule\MaintenanceScheduleSnapshot;
use Maintenance\Application\Port\Inbound\MaintenanceSchedulePort;
use Maintenance\Application\Port\Outbound\Compliance\MaintenanceCompliancePolicyPort;
use Maintenance\Application\Port\Outbound\Directory\MaintenanceEquipmentDirectoryPort;
use Maintenance\Application\Port\Outbound\Schedule\{MaintenanceScheduleLockPort, MaintenanceScheduleRepositoryPort};
use Maintenance\Domain\Service\MaintenanceScheduleRecomputePolicy;
use Shared\Application\Port\Outbound\ClockPort;

use function array_keys;
use function array_map;
use function array_push;

/**
 * Service MaintenanceScheduleService.
 *
 * Implements the inbound synchronization port: recomputes one equipment's
 * maintenance schedule after an inspection closes.
 *
 * @category Service
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
    private ?\Maintenance\Application\Port\Inbound\MaintenancePlanAuthorityPort $planAuthority = null,
  ) {
  }

  // #endregion
  // #region Methods
  /**
   * Method onInspectionClosed
   *
   * Handles inspection closed the supplied event.
   *
   * @access public
   *
   * @param string $organizationId the organization identifier
   * @param string $equipmentId the equipment identifier
   * @param DateTimeImmutable $closedAt the closed time
   *
   * @return void
   */
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

  /**
   * Reconciles one bounded page with current source reads after taking all schedule locks.
   *
   * @param list<TrackableEquipment> $page equipment identities to refresh
   */
  public function refreshPage(array $page): void
  {
    if ([] === $page) {
      return;
    }
    $scopes = array_map(static fn (TrackableEquipment $equipment): array => ['organizationId' => $equipment->organizationId, 'equipmentId' => $equipment->equipmentId], $page);
    $this->locks->synchronizedBatch($scopes, fn () => $this->refreshLockedPage($page));
  }

  /**
   * Method refreshLockedPage
   *
   * Reloads one bounded page after acquiring all equipment locks and persists its snapshots together.
   *
   * @access private
   *
   * @param list<TrackableEquipment> $page the locked equipment identities
   *
   * @return void
   */
  private function refreshLockedPage(array $page): void
  {
    $ids = array_map(static fn (TrackableEquipment $equipment): string => $equipment->equipmentId, $page);
    $current = $this->directory->findEquipmentByIds($ids);
    $groups = [];
    $seen = [];
    foreach ($current as $equipment) {
      $groups[$equipment->organizationId][] = $equipment;
      $seen[$equipment->equipmentId] = true;
    }
    $policies = $this->compliancePolicy->compliancePolicies(array_keys($groups));
    $snapshots = [];
    $now = $this->clock->now();
    foreach ($groups as $organizationId => $equipmentGroup) {
      array_push($snapshots, ...$this->refreshOrganizationEquipment($organizationId, $equipmentGroup, $policies[$organizationId] ?? null, $now));
    }
    foreach ($page as $equipment) {
      if (!isset($seen[$equipment->equipmentId])) {
        if ($this->planAuthority?->usesPlans($equipment->organizationId)) {
          $this->planAuthority->refreshControlProjection($equipment->organizationId, $equipment->equipmentId);

          continue;
        }
        $this->schedules->removeByOrganizationAndEquipment($equipment->organizationId, $equipment->equipmentId);
      }
    }
    $this->schedules->saveBatch($snapshots);
  }

  /**
   * Method refreshOrganizationEquipment
   *
   * Combines current inspection history and schedule state under the organization's compliance policy.
   *
   * @access private
   *
   * @param string $organizationId the owning organization identifier
   * @param list<TrackableEquipment> $equipmentGroup the current equipment within the locked page
   * @param ?MaintenanceCompliancePolicy $compliance the batch policy, or null to reload it
   * @param DateTimeImmutable $now the page's shared evaluation instant
   *
   * @return list<MaintenanceScheduleSnapshot> the snapshots to persist in the page transaction
   */
  private function refreshOrganizationEquipment(string $organizationId, array $equipmentGroup, ?MaintenanceCompliancePolicy $compliance, DateTimeImmutable $now): array
  {
    if ($this->planAuthority?->usesPlans($organizationId)) {
      foreach ($equipmentGroup as $equipment) {
        $this->planAuthority->refreshControlProjection($organizationId, $equipment->equipmentId);
      }

      return [];
    }
    $equipmentIds = array_map(static fn (TrackableEquipment $equipment): string => $equipment->equipmentId, $equipmentGroup);
    $existing = $this->schedules->findForEquipment($organizationId, $equipmentIds);
    $history = $this->inspectionHistory->latestClosedAtForEquipment($organizationId, $equipmentIds);
    $compliance ??= $this->compliancePolicy->compliancePolicy($organizationId);
    $snapshots = [];
    foreach ($equipmentGroup as $equipment) {
      if ('decommissioned' === $equipment->status) {
        $this->schedules->removeByOrganizationAndEquipment($organizationId, $equipment->equipmentId);

        continue;
      }
      $schedule = $existing[$equipment->equipmentId] ?? null;
      $lastClosedAt = $schedule?->lastInspectionClosedAt;
      $published = $history[$equipment->equipmentId] ?? null;
      if (null !== $published && (null === $lastClosedAt || $published > $lastClosedAt)) {
        $lastClosedAt = $published;
      }
      $interval = $this->policy->resolveEffectiveInterval($schedule?->intervalOverride, $compliance->periodicityFor($equipment->equipmentType));
      $nextDueAt = $this->policy->computeNextDueAt($lastClosedAt, $interval);
      $reset = $this->policy->shouldResetRemindedFor($schedule?->nextDueAt, $nextDueAt);
      $snapshots[] = new MaintenanceScheduleSnapshot(
        $schedule?->id,
        $organizationId,
        $equipment->equipmentId,
        $equipment->facilityId,
        $equipment->equipmentType,
        $schedule?->intervalOverride,
        $lastClosedAt,
        $nextDueAt,
        $this->policy->computeDueStatus($nextDueAt, $interval, $now, $compliance->reminderWindowDays)->value,
        $schedule?->lastRemindedAt,
        $reset ? null : $schedule?->remindedFor,
        $now,
      );
    }

    return $snapshots;
  }

  /**
   * Method recompute
   *
   * Recomputes the next maintenance schedule after an intervention closes.
   *
   * @access private
   *
   * @param string $organizationId the organization identifier
   * @param string $equipmentId the equipment identifier
   * @param ?DateTimeImmutable $closedAt the optional closed time
   *
   * @return void
   */
  private function recompute(string $organizationId, string $equipmentId, ?DateTimeImmutable $closedAt = null): void
  {
    if ($this->planAuthority?->usesPlans($organizationId)) {
      $this->planAuthority->refreshControlProjection($organizationId, $equipmentId);

      return;
    }
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
