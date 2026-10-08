<?php

declare(strict_types=1);

namespace Maintenance\Application\Service;

use Intervention\Application\Contract\Draft\InterventionMaintenanceWork;
use Intervention\Application\Port\Inbound\InterventionMaintenanceWorkPort;
use Maintenance\Application\Contract\Compliance\MaintenanceCompliancePolicy;
use Maintenance\Application\Contract\Plan\{MaintenanceOccurrenceState, MaintenancePlanState};
use Maintenance\Application\Contract\Schedule\MaintenanceScheduleView;
use Maintenance\Application\Port\Outbound\Compliance\MaintenanceCompliancePolicyPort;
use Maintenance\Application\Port\Outbound\Plan\{MaintenanceLegacyPlanPort, MaintenancePlanStorePort};
use Maintenance\Application\Port\Outbound\Schedule\MaintenanceScheduleRepositoryPort;
use Maintenance\Domain\Exception\MaintenanceValidationException;
use Maintenance\Domain\ValueObject\PlanCadence;
use Shared\Application\Port\Outbound\{ClockPort, UuidGeneratorPort};

use function array_map;
use function count;

/**
 * Class MaintenanceLegacyPlanService
 *
 * Owns reconciliation and handover of historical cadence sources under the
 * transaction and organization lock held by the plan use case.
 *
 * @category Service
 */
final readonly class MaintenanceLegacyPlanService implements MaintenanceLegacyPlanPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Supplies historical sources and live-work mapping on the owning main transaction.
   *
   * @access public
   *
   * @param MaintenancePlanStorePort $plans the plan and engine state store
   * @param MaintenanceScheduleRepositoryPort $schedules the original schedule source
   * @param MaintenanceCompliancePolicyPort $compliance the current default cadence source
   * @param InterventionMaintenanceWorkPort $work the open intervention work directory
   * @param ClockPort $clock the transaction's evaluation clock
   * @param UuidGeneratorPort $ids the occurrence and plan identity generator
   *
   * @return void
   */
  public function __construct(
    private MaintenancePlanStorePort $plans,
    private MaintenanceScheduleRepositoryPort $schedules,
    private MaintenanceCompliancePolicyPort $compliance,
    private InterventionMaintenanceWorkPort $work,
    private ClockPort $clock,
    private UuidGeneratorPort $ids,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method prepare
   *
   * Reconciles source rows in bounded pages, preserving source identities and history.
   *
   * @access public
   *
   * @param string $organizationId the locked organization
   *
   * @return int the prepared source count
   */
  public function prepare(string $organizationId): int
  {
    if ('plans' === $this->plans->engineMode($organizationId)) {
      return $this->plans->count($organizationId);
    }
    $policy = $this->compliance->compliancePolicy($organizationId);
    $page = 1;
    $prepared = 0;
    do {
      $schedules = $this->schedules->list($organizationId, null, null, null, null, $page++, 200);
      foreach ($schedules->items as $schedule) {
        if ($this->prepareSchedule($organizationId, $schedule, $policy)) {
          ++$prepared;
        }
      }
    } while (200 === count($schedules->items));
    $offset = 0;
    do {
      $plans = $this->plans->list($organizationId, 200, $offset, includeArchived: true);
      foreach ($plans as $plan) {
        $this->archiveUnavailableSource($plan, $policy);
      }
      $offset += 200;
    } while (200 === count($plans));

    return $prepared;
  }

  /**
   * Method activate
   *
   * Maps each unambiguous live historical control before committing engine authority.
   *
   * @access public
   *
   * @param string $organizationId the locked organization
   *
   * @return int the preparation count
   */
  public function activate(string $organizationId): int
  {
    if ('plans' === $this->plans->engineMode($organizationId)) {
      return $this->plans->count($organizationId);
    }
    $prepared = $this->prepare($organizationId);
    $offset = 0;
    do {
      $page = $this->plans->list($organizationId, 200, $offset);
      $equipmentIds = array_map(static fn (MaintenancePlanState $plan): string => $plan->equipmentId, $page);
      $candidates = $this->work->findOpenLegacyInspectionWork($organizationId, $equipmentIds);
      foreach ($page as $plan) {
        $this->activatePlan($organizationId, $plan, $candidates[$plan->equipmentId] ?? []);
      }
      $offset += 200;
    } while (200 === count($page));
    $this->plans->activateEngine($organizationId, $this->clock->now());

    return $prepared;
  }

  /**
   * Method refreshPolicy
   *
   * Keeps historical override/default precedence without moving an open occurrence.
   *
   * @access public
   *
   * @param MaintenancePlanState $plan the candidate operation
   * @param bool $save whether changes may be persisted before the caller finishes paging
   *
   * @return bool whether a cadence source remains available
   */
  public function refreshPolicy(MaintenancePlanState $plan, bool $save = true): bool
  {
    if (null === $plan->legacyScheduleId) {
      return true;
    }
    $schedule = $this->schedules->findById($plan->legacyScheduleId);
    $interval = null === $schedule ? null : ($schedule->intervalOverride ?? $this->compliance->compliancePolicy($plan->organizationId)->periodicityFor($plan->equipmentType));
    if (null === $interval) {
      return false;
    }
    if ($interval !== $plan->interval && null === $this->plans->openOccurrence($plan->organizationId, $plan->id)) {
      $cadence = PlanCadence::legacyFromString($interval);
      $plan->interval = $cadence->value;
      if (null !== $plan->lastCompletedAt) {
        $plan->nextDueAt = $cadence->addTo($plan->lastCompletedAt);
      }
      $plan->updatedAt = $this->clock->now();
      if ($save) {
        $this->plans->save($plan);
      }
    }

    return true;
  }

  /**
   * Method prepareSchedule
   *
   * Preserves original source dates and existing plan history without enabling generation.
   *
   * @access private
   *
   * @param string $organizationId the owning organization
   * @param MaintenanceScheduleView $schedule the historical schedule
   * @param MaintenanceCompliancePolicy $policy the organization's cadence policy
   *
   * @return bool whether the source has an effective cadence
   */
  private function prepareSchedule(string $organizationId, MaintenanceScheduleView $schedule, MaintenanceCompliancePolicy $policy): bool
  {
    $interval = $schedule->intervalOverride ?? $policy->periodicityFor($schedule->equipmentType);
    if (null === $interval) {
      return false;
    }
    PlanCadence::legacyFromString($interval);
    $now = $this->clock->now();
    $existing = $this->plans->findByLegacySchedule($organizationId, $schedule->id);
    $plan = new MaintenancePlanState($existing->id ?? $this->ids->generate(), $organizationId, $schedule->equipmentId, $schedule->facilityId, $schedule->equipmentType, $existing->name ?? 'Periodic control', 'control', $interval, 'legacy', $schedule->nextDueAt ?? $existing?->anchorAt, $schedule->nextDueAt ?? $existing?->nextDueAt, false, $schedule->id, $schedule->lastInspectionClosedAt, $existing?->archivedAt, $existing->createdAt ?? $now, $now);
    $this->plans->save($plan);

    return true;
  }

  /**
   * Method archiveUnavailableSource
   *
   * Stops historical candidates whose original schedule or effective cadence disappeared.
   *
   * @access private
   *
   * @param MaintenancePlanState $plan the prepared candidate
   * @param MaintenanceCompliancePolicy $policy the current cadence policy
   *
   * @return void
   */
  private function archiveUnavailableSource(MaintenancePlanState $plan, MaintenanceCompliancePolicy $policy): void
  {
    if (null === $plan->legacyScheduleId) {
      return;
    }
    $schedule = $this->schedules->findById($plan->legacyScheduleId);
    if (null === $schedule || null === ($schedule->intervalOverride ?? $policy->periodicityFor($schedule->equipmentType))) {
      $plan->active = false;
      $plan->archivedAt ??= $this->clock->now();
      $plan->updatedAt = $this->clock->now();
      $this->plans->save($plan);
    }
  }

  /**
   * Method activatePlan
   *
   * Reserves the stable occurrence before linking a single existing control task.
   *
   * @access private
   *
   * @param string $organizationId the locked organization
   * @param MaintenancePlanState $plan the prepared candidate
   * @param list<InterventionMaintenanceWork> $matches the current open legacy tasks
   *
   * @return void
   */
  private function activatePlan(string $organizationId, MaintenancePlanState $plan, array $matches): void
  {
    if (null !== $plan->archivedAt) {
      return;
    }
    if (null === $plan->legacyScheduleId) {
      $this->assertNewControlMapping($organizationId, $plan, $matches);

      return;
    }
    if (count($matches) > 1) {
      throw new MaintenanceValidationException('Ambiguous legacy work for equipment ' . $plan->equipmentId . '; resolve duplicate open controls before activation.');
    }
    if (1 === count($matches)) {
      $match = $matches[0];
      if ('submitted' === $this->work->status($organizationId, $match->interventionId)) {
        throw new MaintenanceValidationException('Complete the submitted legacy intervention before activating plans.');
      }
      $occurrence = new MaintenanceOccurrenceState($this->ids->generate(), $plan->id, $organizationId, $plan->nextDueAt ?? $this->clock->now(), 'open', 1, $match->interventionId, null, null, $this->clock->now(), $this->clock->now(), $match->number);
      $this->plans->saveOccurrence($occurrence);
      $this->work->attachOccurrence($organizationId, $match->workItemId, $plan->id, $occurrence->id, 'control');
    }
    $plan->active = true;
    $plan->updatedAt = $this->clock->now();
    $this->plans->save($plan);
  }

  /**
   * Method assertNewControlMapping
   *
   * Refuses live control work that cannot be attributed to a historical plan safely.
   *
   * @access private
   *
   * @param string $organizationId the locked organization
   * @param MaintenancePlanState $plan the independent candidate
   * @param list<InterventionMaintenanceWork> $matches the equipment's open historical tasks
   *
   * @return void
   */
  private function assertNewControlMapping(string $organizationId, MaintenancePlanState $plan, array $matches): void
  {
    if ('control' === $plan->operationKind && [] !== $matches) {
      $schedule = $this->schedules->findByOrganizationAndEquipment($organizationId, $plan->equipmentId);
      if (null === $schedule || null === $this->plans->findByLegacySchedule($organizationId, $schedule->id)) {
        throw new MaintenanceValidationException('Existing control work cannot be mapped safely to the new operation; resolve it before activation.');
      }
    }
  }
  // #endregion
}
