<?php

declare(strict_types=1);

namespace Maintenance\Application\UseCase\Command\Schedule\SetMaintenanceScheduleOverride;

use Maintenance\Application\Contract\Schedule\MaintenanceScheduleSnapshot;
use Maintenance\Application\Port\Outbound\Compliance\MaintenanceCompliancePolicyPort;
use Maintenance\Application\Port\Outbound\Schedule\MaintenanceScheduleRepositoryPort;
use Maintenance\Domain\Event\Schedule\MaintenanceScheduleOverriddenEvent;
use Maintenance\Domain\Exception\{MaintenanceAccessDeniedException, MaintenanceNotFoundException, MaintenanceValidationException};
use Maintenance\Domain\Service\MaintenanceScheduleRecomputePolicy;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{ClockPort, EventDispatcherPort};
use Shared\Domain\Exception\InvalidValueException;

/**
 * UseCase SetMaintenanceScheduleOverrideHandler.
 *
 * Sets or clears a maintenance schedule's per-equipment periodicity
 * override and recomputes its next due date and due status accordingly.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SetMaintenanceScheduleOverrideHandler implements CommandHandler
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param MaintenanceScheduleRepositoryPort $schedules the schedule repository port
   * @param MaintenanceCompliancePolicyPort $compliancePolicy the cross-module organization compliance policy port
   * @param MaintenanceScheduleRecomputePolicy $policy the pure recompute policy
   * @param OrganizationAuthorizationPort $authorization the organization authorization port
   * @param EventDispatcherPort $eventDispatcher the event dispatcher port
   * @param ClockPort $clock the clock port
   */
  public function __construct(
    private MaintenanceScheduleRepositoryPort $schedules,
    private MaintenanceCompliancePolicyPort $compliancePolicy,
    private MaintenanceScheduleRecomputePolicy $policy,
    private OrganizationAuthorizationPort $authorization,
    private EventDispatcherPort $eventDispatcher,
    private ClockPort $clock,
  ) {
  }

  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param SetMaintenanceScheduleOverrideCommand $command the command value
   *
   * @return SetMaintenanceScheduleOverrideResult the command result
   */
  public function __invoke(SetMaintenanceScheduleOverrideCommand $command): SetMaintenanceScheduleOverrideResult
  {
    $schedule = $this->schedules->findById($command->scheduleId);
    if (null === $schedule) {
      throw MaintenanceNotFoundException::withId($command->scheduleId);
    }

    $decision = $this->authorization->resolveAccess($command->userId, $schedule->organizationId, 'organization.maintenance.manage');
    if ($decision->isOutsideScope()) {
      throw MaintenanceNotFoundException::withId($command->scheduleId);
    }
    if (!$decision->isGranted()) {
      throw new MaintenanceAccessDeniedException('Missing organization.maintenance.manage permission.');
    }

    $compliancePolicy = $this->compliancePolicy->compliancePolicy($schedule->organizationId);

    try {
      $effectiveInterval = $this->policy->resolveEffectiveInterval(
        $command->intervalOverride,
        $compliancePolicy->periodicityFor($schedule->equipmentType),
      );
    } catch (InvalidValueException $exception) {
      throw new MaintenanceValidationException($exception->getMessage(), 0, $exception);
    }

    $nextDueAt = $this->policy->computeNextDueAt($schedule->lastInspectionClosedAt, $effectiveInterval);
    $dueStatus = $this->policy->computeDueStatus(
      $nextDueAt,
      $effectiveInterval,
      $this->clock->now(),
      $compliancePolicy->reminderWindowDays,
    );
    $resetRemindedFor = $this->policy->shouldResetRemindedFor($schedule->nextDueAt, $nextDueAt);

    $updated = $this->schedules->save(new MaintenanceScheduleSnapshot(
      id: $schedule->id,
      organizationId: $schedule->organizationId,
      equipmentId: $schedule->equipmentId,
      facilityId: $schedule->facilityId,
      equipmentType: $schedule->equipmentType,
      intervalOverride: $command->intervalOverride,
      lastInspectionClosedAt: $schedule->lastInspectionClosedAt,
      nextDueAt: $nextDueAt,
      dueStatus: $dueStatus->value,
      lastRemindedAt: $schedule->lastRemindedAt,
      remindedFor: $resetRemindedFor ? null : $schedule->remindedFor,
    ));

    $this->eventDispatcher->dispatch(new MaintenanceScheduleOverriddenEvent(
      organizationId: $updated->organizationId,
      scheduleId: $updated->id,
      equipmentId: $updated->equipmentId,
      intervalOverride: $updated->intervalOverride,
      actorUserId: $command->userId,
    ));

    return new SetMaintenanceScheduleOverrideResult($updated);
  }
}
