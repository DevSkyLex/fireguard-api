<?php

declare(strict_types=1);

namespace Maintenance\Application\UseCase\Command\Plan\ManageMaintenancePlan;

use DateTimeImmutable;
use DateTimeZone;
use Intervention\Application\Contract\Draft\{CreateInterventionDraftRequest, InterventionDraftWorkItem};
use Intervention\Application\Port\Inbound\{InterventionDraftFactoryPort, InterventionMaintenanceWorkPort};
use Maintenance\Application\Contract\Directory\TrackableEquipment;
use Maintenance\Application\Contract\Plan\{MaintenanceOccurrenceState, MaintenancePlanDetails, MaintenancePlanState};
use Maintenance\Application\Contract\Schedule\{MaintenanceScheduleSnapshot, MaintenanceScheduleView};
use Maintenance\Application\Port\Outbound\Compliance\MaintenanceCompliancePolicyPort;
use Maintenance\Application\Port\Outbound\Directory\{MaintenanceEquipmentDirectoryPort, MaintenanceFacilityLifecyclePort};
use Maintenance\Application\Port\Outbound\Plan\{MaintenanceLegacyPlanPort, MaintenancePlanStorePort};
use Maintenance\Application\Port\Outbound\Schedule\MaintenanceScheduleRepositoryPort;
use Maintenance\Application\Service\MaintenancePlanModelFactory;
use Maintenance\Domain\Event\Campaign\MaintenanceCampaignGeneratedEvent;
use Maintenance\Domain\Event\Reminder\MaintenanceReminderRequestedEvent;
use Maintenance\Domain\Exception\{MaintenanceAccessDeniedException, MaintenanceNotFoundException, MaintenanceValidationException};
use Maintenance\Domain\Model\MaintenancePlan;
use Maintenance\Domain\Service\MaintenanceScheduleRecomputePolicy;
use Maintenance\Domain\ValueObject\{MaintenanceControlDates, MaintenanceDueStatus, MaintenanceOperationKind, MaintenancePlanIdentity, PeriodicityInterval, PlanCadence};
use Organization\Application\Port\Inbound\{OrganizationAuthorizationPort, OrganizationWorkforceDirectoryPort};
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{ClockPort, EventDispatcherPort, UuidGeneratorPort};

use function count;
use function in_array;
use function max;

/** Coordinates plans, reservations, drafts and publication on one locked main transaction. */
final readonly class ManageMaintenancePlanHandler implements CommandHandler
{
  public function __construct(
    private MaintenancePlanStorePort $plans,
    private MaintenanceScheduleRepositoryPort $schedules,
    private MaintenanceEquipmentDirectoryPort $equipment,
    private MaintenanceFacilityLifecyclePort $facilities,
    private MaintenanceCompliancePolicyPort $compliance,
    private InterventionDraftFactoryPort $drafts,
    private InterventionMaintenanceWorkPort $work,
    private OrganizationAuthorizationPort $authorization,
    private ClockPort $clock,
    private UuidGeneratorPort $ids,
    private EventDispatcherPort $events,
    private MaintenanceLegacyPlanPort $legacy,
    private int $maxCampaignWorkItems = 25,
    private ?OrganizationWorkforceDirectoryPort $regional = null,
  ) {
  }

  public function __invoke(ManageMaintenancePlanCommand $command): ManageMaintenancePlanResult
  {
    $system = in_array($command->action, ['validate_result', 'acknowledge_result', 'refresh_projection'], true);
    if (!$system) {
      $this->authorize($command->organizationId, $command->actorUserId, 'organization.maintenance.manage');
    }
    if (in_array($command->action, ['generate', 'campaign', 'activate'], true)) {
      $this->authorize($command->organizationId, $command->actorUserId, 'organization.interventions.plan');
    }

    return $this->plans->synchronized($command->organizationId, fn (): ManageMaintenancePlanResult => match ($command->action) {
      'create' => $this->create($command),
      'update', 'archive' => $this->update($command),
      'set_legacy_override' => $this->legacyOverride($command),
      'prepare_legacy' => new ManageMaintenancePlanResult(mode: $this->plans->engineMode($command->organizationId), preparedCount: $this->legacy->prepare($command->organizationId)),
      'activate' => $this->activate($command->organizationId),
      'generate' => $this->generate($command),
      'campaign' => $this->campaign($command),
      'validate_result', 'acknowledge_result' => $this->result($command),
      'refresh_projection' => $this->projection($command->organizationId, $command->equipmentId ?? ''),
      default => throw new MaintenanceValidationException('Unknown maintenance plan action.'),
    });
  }

  private function authorize(string $organizationId, ?string $actorUserId, string $permission): void
  {
    if (null === $actorUserId) {
      throw new MaintenanceAccessDeniedException('An authenticated actor is required.');
    }
    $decision = $this->authorization->resolveAccess($actorUserId, $organizationId, $permission);
    if ($decision->isOutsideScope()) {
      throw MaintenanceNotFoundException::forOrganizationScope($organizationId);
    }
    if (!$decision->isGranted()) {
      throw new MaintenanceAccessDeniedException('Missing permission: ' . $permission);
    }
  }

  private function create(ManageMaintenancePlanCommand $command): ManageMaintenancePlanResult
  {
    $equipment = $this->equipment->findEquipment($command->equipmentId ?? '');
    if (null === $equipment || $equipment->organizationId !== $command->organizationId) {
      throw MaintenanceNotFoundException::withId($command->equipmentId ?? '');
    }
    if ((null === $command->anchorAt && null === $command->anchorOn) || null === $command->interval || null === $command->name || null === $command->operationKind) {
      throw new MaintenanceValidationException('Equipment, name, kind, interval and calendar anchor are required.');
    }
    $now = $this->clock->now();
    $regionalContext = null === $this->regional ? null : $this->regional->context($command->organizationId);
    $timezone = null === $regionalContext ? 'UTC' : $regionalContext->timezone;
    $zone = new DateTimeZone($timezone);
    $anchor = $this->calendarDate($command->anchorAt, $command->anchorOn, $zone) ?? throw new MaintenanceValidationException('A first calendar date is required.');
    $next = $this->calendarDate($command->nextDueAt, $command->nextDueOn, $zone);
    $model = MaintenancePlan::create(new MaintenancePlanIdentity($this->ids->generate(), $command->organizationId, $equipment->equipmentId), $command->name, MaintenanceOperationKind::from($command->operationKind), PlanCadence::fromString($command->interval), $anchor, $now);
    $plan = new MaintenancePlanState($model->id, $model->organizationId, $model->equipmentId, $equipment->facilityId, $equipment->equipmentType, $model->name, $model->kind->value, $model->cadence->value, 'fixed', $model->anchorAt, $next ?? $model->nextDueAt(), $command->active ?? false, null, null, null, $now, $now, $timezone);
    MaintenancePlanModelFactory::plan($plan);
    $this->plans->save($plan);
    $this->projection($plan->organizationId, $plan->equipmentId);

    return $this->details($plan);
  }

  private function update(ManageMaintenancePlanCommand $command): ManageMaintenancePlanResult
  {
    $plan = $this->requirePlan($command->organizationId, $command->planId);
    $open = $this->plans->openOccurrence($plan->organizationId, $plan->id);
    if ('archive' === $command->action) {
      if (null !== $open && null !== $open->interventionId && !in_array($this->work->status($plan->organizationId, $open->interventionId), ['abandoned', 'published'], true)) {
        throw new MaintenanceValidationException('Resolve or abandon the open work before archiving its plan.');
      }
      $plan->active = false;
      $plan->archivedAt = $this->clock->now();
      $plan->updatedAt = $this->clock->now();
      $this->plans->save($plan);
      $this->projection($plan->organizationId, $plan->equipmentId);

      return $this->details($plan);
    }
    if (null !== $plan->archivedAt) {
      throw new MaintenanceValidationException('An archived plan cannot be edited.');
    }
    if (null !== $open && (null !== $command->interval || null !== $command->anchorAt || null !== $command->nextDueAt || null !== $command->anchorOn || null !== $command->nextDueOn)) {
      throw new MaintenanceValidationException('An open occurrence retains its cadence and original due date.');
    }
    $plan->name = $command->name ?? $plan->name;
    $plan->interval = $command->interval ?? $plan->interval;
    $plan->anchorAt = $this->calendarDate($command->anchorAt, $command->anchorOn, new DateTimeZone($plan->calendarTimezone)) ?? $plan->anchorAt;
    $plan->nextDueAt = $this->calendarDate($command->nextDueAt, $command->nextDueOn, new DateTimeZone($plan->calendarTimezone)) ?? $plan->nextDueAt;
    $plan->active = $command->active ?? $plan->active;
    MaintenancePlanModelFactory::plan($plan);
    if (null !== $plan->legacyScheduleId && null !== $command->interval) {
      $source = $this->schedules->findById($plan->legacyScheduleId) ?? throw MaintenanceNotFoundException::withId($plan->legacyScheduleId);
      if (null !== $plan->lastCompletedAt && null === $command->nextDueAt && null === $command->nextDueOn) {
        $plan->nextDueAt = PlanCadence::legacyFromString($plan->interval)->addTo($plan->lastCompletedAt);
      }
      $this->schedules->save(new MaintenanceScheduleSnapshot($source->id, $source->organizationId, $source->equipmentId, $source->facilityId, $source->equipmentType, $plan->interval, $source->lastInspectionClosedAt, $source->nextDueAt, $source->dueStatus, $source->lastRemindedAt, $source->remindedFor, $this->clock->now()));
    }
    $plan->updatedAt = $this->clock->now();
    $this->plans->save($plan);
    $this->projection($plan->organizationId, $plan->equipmentId);

    return $this->details($plan);
  }

  private function legacyOverride(ManageMaintenancePlanCommand $command): ManageMaintenancePlanResult
  {
    $plan = $this->plans->findByLegacySchedule($command->organizationId, $command->planId ?? '') ?? throw new MaintenanceValidationException('This control projection has no historical plan; edit its operation instead.');
    if (null !== $this->plans->openOccurrence($plan->organizationId, $plan->id)) {
      throw new MaintenanceValidationException('An open occurrence retains its cadence; resolve it before changing the historical override.');
    }
    $schedule = $this->schedules->findById($plan->legacyScheduleId ?? '') ?? throw MaintenanceNotFoundException::withId($command->planId ?? '');
    $effective = $command->interval ?? $this->compliance->compliancePolicy($plan->organizationId)->periodicityFor($plan->equipmentType);
    if (null !== $effective) {
      $plan->interval = PlanCadence::legacyFromString($effective)->value;
      $plan->nextDueAt = null === $plan->lastCompletedAt ? null : PlanCadence::legacyFromString($effective)->addTo($plan->lastCompletedAt);
    }
    $plan->active = null !== $effective;
    $plan->updatedAt = $this->clock->now();
    $this->plans->save($plan);
    $this->schedules->save(new MaintenanceScheduleSnapshot($schedule->id, $schedule->organizationId, $schedule->equipmentId, $schedule->facilityId, $schedule->equipmentType, $command->interval, $schedule->lastInspectionClosedAt, $schedule->nextDueAt, $schedule->dueStatus, $schedule->lastRemindedAt, $schedule->remindedFor, $this->clock->now()));
    $this->projection($plan->organizationId, $plan->equipmentId);
    $this->events->dispatch(new \Maintenance\Domain\Event\Schedule\MaintenanceScheduleOverriddenEvent($plan->organizationId, $schedule->id, $plan->equipmentId, $command->interval, $command->actorUserId ?? ''));

    return $this->details($plan);
  }

  private function activate(string $organizationId): ManageMaintenancePlanResult
  {
    if ('plans' === $this->plans->engineMode($organizationId)) {
      return new ManageMaintenancePlanResult(mode: 'plans', preparedCount: $this->plans->count($organizationId));
    }
    $prepared = $this->legacy->activate($organizationId);
    $offset = 0;
    do {
      $page = $this->plans->list($organizationId, 200, $offset);
      foreach ($page as $plan) {
        $this->projection($organizationId, $plan->equipmentId);
      }
      $offset += 200;
    } while (200 === count($page));

    return new ManageMaintenancePlanResult(mode: 'plans', preparedCount: $prepared);
  }

  private function generate(ManageMaintenancePlanCommand $command): ManageMaintenancePlanResult
  {
    $plan = $this->requirePlan($command->organizationId, $command->planId);
    if ('plans' !== $this->plans->engineMode($command->organizationId)) {
      throw new MaintenanceValidationException('Activate the prepared plans engine before generating work.');
    }
    $this->assertTrackable($plan);
    $open = $this->plans->openOccurrence($plan->organizationId, $plan->id);
    if (null !== $open && null !== $open->interventionId && !$command->retry) {
      return new ManageMaintenancePlanResult(occurrenceId: $open->id, interventionId: $open->interventionId, number: $open->number, workItemsCount: 1, replayed: true, mode: 'plans');
    }
    if ($command->retry && (null === $open || !$this->canRetry($open))) {
      throw new MaintenanceValidationException('Retry requires an abandoned or unsuccessful current attempt.');
    }

    return $this->generatePlans([$plan], $command->name ?? $plan->name, $command->actorUserId, $command->retry);
  }

  private function campaign(ManageMaintenancePlanCommand $command): ManageMaintenancePlanResult
  {
    if ('plans' !== $this->plans->engineMode($command->organizationId)) {
      throw new MaintenanceValidationException('The organization still uses historical schedules.');
    }
    $selected = [];
    $offset = 0;
    $before = $command->dueBefore ?? $this->clock->now();
    do {
      $page = $this->plans->list($command->organizationId, 200, $offset, operationKind: 'control');
      foreach ($page as $plan) {
        if (!$this->campaignCandidate($plan, $command, $before)) {
          continue;
        }
        $selected[] = $plan;
        if (count($selected) > $this->maxCampaignWorkItems) {
          throw new MaintenanceValidationException('Campaign exceeds the synchronous work-item limit; narrow the filters.');
        }
      }
      $offset += 200;
    } while (200 === count($page));
    if ([] === $selected) {
      throw new MaintenanceValidationException('No unassigned control occurrences match the campaign.');
    }
    foreach ($selected as $plan) {
      $this->plans->save($plan);
    }

    return $this->generatePlans($selected, $command->name ?? 'Control campaign', $command->actorUserId, false);
  }

  /**
   * Method campaignCandidate
   *
   * Refreshes current scope and legacy cadence before applying campaign filters,
   * deferring saves until all due-ordered pages have been read.
   *
   * @access private
   *
   * @param MaintenancePlanState $plan the control candidate
   * @param ManageMaintenancePlanCommand $command the campaign filters
   * @param DateTimeImmutable $before the inclusive due cutoff
   *
   * @return bool whether unassigned work can be reserved for this control
   */
  private function campaignCandidate(MaintenancePlanState $plan, ManageMaintenancePlanCommand $command, DateTimeImmutable $before): bool
  {
    return $this->trackable($plan, false)
      && (null === $command->facilityId || $plan->facilityId === $command->facilityId)
      && (null === $command->equipmentType || $plan->equipmentType === $command->equipmentType)
      && (null === $plan->nextDueAt || $plan->nextDueAt <= $before)
      && null === $this->plans->openOccurrence($plan->organizationId, $plan->id);
  }

  /**
   * @param list<MaintenancePlanState> $plans
   */
  private function generatePlans(array $plans, string $name, ?string $actorUserId, bool $retry): ManageMaintenancePlanResult
  {
    $occurrences = [];
    $items = [];
    foreach ($plans as $plan) {
      $open = $this->plans->openOccurrence($plan->organizationId, $plan->id);
      $occurrence = $open ?? new MaintenanceOccurrenceState($this->ids->generate(), $plan->id, $plan->organizationId, $plan->nextDueAt ?? $this->clock->now(), 'open', 0, null, null, null, $this->clock->now(), $this->clock->now());
      $this->plans->saveOccurrence($occurrence);
      $occurrences[] = $occurrence;
      $items[] = new InterventionDraftWorkItem(action: 'control' === $plan->operationKind ? 'inspection' : 'maintenance', target: '/api/equipment/' . $plan->equipmentId, operationId: $plan->id, occurrenceId: $occurrence->id, operationKind: $plan->operationKind);
    }
    $first = $plans[0];
    $draft = $this->drafts->create(new CreateInterventionDraftRequest(organizationId: $first->organizationId, type: 'control' === $first->operationKind ? 'inspection_campaign' : 'preventive_maintenance', name: $name, origin: 'maintenance:plan', workItems: $items, actorUserId: $actorUserId));
    foreach ($occurrences as $occurrence) {
      $model = MaintenancePlanModelFactory::occurrence($occurrence);
      $retry ? $model->retryAttempt($draft->interventionId) : $model->beginAttempt($draft->interventionId);
      $occurrence->attempt = $model->attempt();
      $occurrence->interventionId = $model->interventionId();
      $occurrence->resultId = $model->resultId();
      $occurrence->number = $draft->number;
      $occurrence->updatedAt = $this->clock->now();
      $this->plans->saveOccurrence($occurrence);
    }
    $this->events->dispatch(new MaintenanceCampaignGeneratedEvent($first->organizationId, $draft->interventionId, $draft->workItemsCount, $actorUserId ?? ''));

    return new ManageMaintenancePlanResult(mode: 'plans', occurrenceId: $occurrences[0]->id, interventionId: $draft->interventionId, number: $draft->number, workItemsCount: $draft->workItemsCount);
  }

  private function result(ManageMaintenancePlanCommand $command): ManageMaintenancePlanResult
  {
    $result = $command->operationResult ?? throw new MaintenanceValidationException('A validated operation result is required.');
    $occurrence = $this->plans->findOccurrence($result->organizationId, $result->occurrenceId) ?? throw MaintenanceNotFoundException::withId($result->occurrenceId);
    $plan = $this->requirePlan($result->organizationId, $occurrence->planId);
    if ($plan->equipmentId !== $result->equipmentId || (null !== $result->operationId && $plan->id !== $result->operationId) || $plan->operationKind !== $result->operationKind || $occurrence->interventionId !== $result->interventionId || !in_array($result->outcome, ['passed', 'failed'], true)) {
      throw new MaintenanceValidationException('The result does not identify the current equipment operation and attempt.');
    }
    if ($this->plans->hasReceipt($result->resultId, $occurrence->id)) {
      return $this->details($plan);
    }
    if ('open' !== $occurrence->status || null !== $occurrence->resultId) {
      throw new MaintenanceValidationException('This occurrence attempt already has a validated result.');
    }
    if ($result->performedAt > $this->clock->now()) {
      throw new MaintenanceValidationException('The result date must belong to this attempt and cannot be in the future.');
    }
    if ('validate_result' === $command->action) {
      return $this->details($plan);
    }
    $model = MaintenancePlanModelFactory::occurrence($occurrence);
    $completed = $model->validateResult(MaintenanceOperationKind::from($plan->operationKind), 'passed' === $result->outcome, $result->resultId, $this->clock->now());
    $occurrence->status = $model->state();
    $occurrence->resultId = $model->resultId();
    $occurrence->completedAt = $model->completedAt();
    $occurrence->updatedAt = $this->clock->now();
    $this->plans->saveOccurrence($occurrence);
    $this->plans->saveReceipt($result);
    if ($completed) {
      $calendar = MaintenancePlanModelFactory::plan($plan);
      $calendar->complete(null === $plan->lastCompletedAt ? $result->performedAt : max($plan->lastCompletedAt, $result->performedAt));
      $plan->nextDueAt = $calendar->nextDueAt();
      $plan->lastCompletedAt = null === $plan->lastCompletedAt ? $result->performedAt : max($plan->lastCompletedAt, $result->performedAt);
      $plan->updatedAt = $this->clock->now();
      $this->plans->save($plan);
      $this->projection($plan->organizationId, $plan->equipmentId);
    }

    return $this->details($plan);
  }

  private function projection(string $organizationId, string $equipmentId): ManageMaintenancePlanResult
  {
    if ('plans' !== $this->plans->engineMode($organizationId)) {
      return new ManageMaintenancePlanResult();
    }
    $equipment = $this->equipment->findEquipment($equipmentId);
    if (null === $equipment || $equipment->organizationId !== $organizationId) {
      $existing = $this->schedules->findByOrganizationAndEquipment($organizationId, $equipmentId);
      if (null !== $existing) {
        $this->schedules->save(new MaintenanceScheduleSnapshot($existing->id, $organizationId, $equipmentId, $existing->facilityId, $existing->equipmentType, $existing->intervalOverride, $existing->lastInspectionClosedAt, null, 'unscheduled', $existing->lastRemindedAt, $existing->remindedFor, $this->clock->now()));
      }

      return new ManageMaintenancePlanResult(mode: 'plans');
    }
    $existing = $this->schedules->findByOrganizationAndEquipment($organizationId, $equipmentId);
    $policy = $this->compliance->compliancePolicy($organizationId);
    $dates = MaintenanceControlDates::empty();
    $offset = 0;
    do {
      $page = $this->plans->list($organizationId, 200, $offset, $equipmentId, 'control');
      foreach ($page as $plan) {
        if (!$plan->active || !$this->legacy->refreshPolicy($plan)) {
          continue;
        }
        $dates = $dates->including($plan->nextDueAt, $plan->lastCompletedAt);
      }
      $offset += 200;
    } while (200 === count($page));
    $next = $dates->nextDueAt;
    $last = $dates->lastCompletedAt;
    $tracked = $dates->tracked;
    if ('decommissioned' === $equipment->status || $this->facilities->isArchived($equipment->facilityId, $organizationId)) {
      $tracked = false;
      $next = null;
    }
    $rules = new MaintenanceScheduleRecomputePolicy();
    $dueStatus = $rules->computeDueStatus($next, $tracked ? PeriodicityInterval::fromString('P1Y') : null, $this->clock->now(), $policy->reminderWindowDays);
    [$remindedFor, $lastRemindedAt] = $this->projectionReminder($equipment, $existing, $next, $tracked, $dueStatus);
    $utc = new DateTimeZone('UTC');
    $this->schedules->save(new MaintenanceScheduleSnapshot($existing?->id, $organizationId, $equipmentId, $equipment->facilityId, $equipment->equipmentType, $existing?->intervalOverride, $last?->setTimezone($utc), $next?->setTimezone($utc), $dueStatus->value, $lastRemindedAt?->setTimezone($utc), $remindedFor?->setTimezone($utc), $this->clock->now()));

    return new ManageMaintenancePlanResult(mode: 'plans');
  }

  /**
   * Method projectionReminder
   *
   * Retains reminder evidence and enqueues only a newly due control projection.
   *
   * @access private
   *
   * @param TrackableEquipment $equipment the current scoped equipment
   * @param ?MaintenanceScheduleView $existing the prior control projection
   * @param ?DateTimeImmutable $next the combined control due date
   * @param bool $tracked whether the current equipment has an eligible control
   * @param MaintenanceDueStatus $dueStatus the current control status
   *
   * @return array{?DateTimeImmutable, ?DateTimeImmutable} the due date already reminded and reminder timestamp
   */
  private function projectionReminder(TrackableEquipment $equipment, ?MaintenanceScheduleView $existing, ?DateTimeImmutable $next, bool $tracked, MaintenanceDueStatus $dueStatus): array
  {
    $remindedFor = $existing?->remindedFor;
    $lastRemindedAt = $existing?->lastRemindedAt;
    $rules = new MaintenanceScheduleRecomputePolicy();
    if ($rules->shouldResetRemindedFor($existing?->nextDueAt, $next)) {
      $remindedFor = null;
    }
    if ($tracked && null !== $next && null === $remindedFor && in_array($dueStatus, [MaintenanceDueStatus::DUE_SOON, MaintenanceDueStatus::OVERDUE], true)) {
      $this->events->dispatch(new MaintenanceReminderRequestedEvent($equipment->organizationId, $equipment->equipmentId, $equipment->facilityId, $next, MaintenanceDueStatus::OVERDUE === $dueStatus));
      $remindedFor = $next;
      $lastRemindedAt = $this->clock->now();
    }

    return [$remindedFor, $lastRemindedAt];
  }

  private function assertTrackable(MaintenancePlanState $plan): void
  {
    if (!$this->trackable($plan)) {
      throw new MaintenanceValidationException('The plan is inactive or its equipment/site is no longer trackable.');
    }
  }

  /**
   * Method trackable
   *
   * Resolves current equipment scope before evaluating its historical cadence source.
   * Campaign scans defer saves so refreshing dates cannot reorder their paged input.
   *
   * @access private
   *
   * @param MaintenancePlanState $plan the candidate operation
   * @param bool $savePolicy whether a changed historical cadence is saved immediately
   *
   * @return bool whether the current operation is eligible for generation
   */
  private function trackable(MaintenancePlanState $plan, bool $savePolicy = true): bool
  {
    $equipment = $this->equipment->findEquipment($plan->equipmentId);
    if (!$plan->active || null !== $plan->archivedAt || null === $equipment || $equipment->organizationId !== $plan->organizationId || 'decommissioned' === $equipment->status || $this->facilities->isArchived($equipment->facilityId, $plan->organizationId)) {
      return false;
    }
    $plan->facilityId = $equipment->facilityId;
    $plan->equipmentType = $equipment->equipmentType;

    return $this->legacy->refreshPolicy($plan, $savePolicy);
  }

  private function requirePlan(string $organizationId, ?string $id): MaintenancePlanState
  {
    return $this->plans->find($organizationId, $id ?? '') ?? throw MaintenanceNotFoundException::withId($id ?? '');
  }

  private function calendarDate(?DateTimeImmutable $instant, ?string $date, DateTimeZone $timezone): ?DateTimeImmutable
  {
    if (null !== $date && null !== $instant) {
      throw new MaintenanceValidationException('Supply either a calendar date or an instant, not both.');
    }
    if (null === $date) {
      return $instant?->setTimezone($timezone);
    }
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $timezone);
    if (false === $parsed || false !== DateTimeImmutable::getLastErrors() || $parsed->format('Y-m-d') !== $date) {
      throw new MaintenanceValidationException('A calendar date must be a real YYYY-MM-DD date.');
    }

    return $parsed;
  }

  private function canRetry(MaintenanceOccurrenceState $occurrence): bool
  {
    return 'open' === $occurrence->status && null !== $occurrence->interventionId && (null !== $occurrence->resultId || in_array($this->work->status($occurrence->organizationId, $occurrence->interventionId), ['abandoned', 'published'], true));
  }

  private function details(MaintenancePlanState $plan): ManageMaintenancePlanResult
  {
    $open = $this->plans->openOccurrence($plan->organizationId, $plan->id);

    return new ManageMaintenancePlanResult(details: new MaintenancePlanDetails($plan, $open, null !== $open && $this->canRetry($open)), mode: $this->plans->engineMode($plan->organizationId));
  }
}
