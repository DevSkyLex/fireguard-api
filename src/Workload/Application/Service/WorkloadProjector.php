<?php

declare(strict_types=1);

namespace Workload\Application\Service;

use DateTimeZone;
use Intervention\Application\Contract\Workload\{InterventionTimeContribution, InterventionWorkContribution};
use Intervention\Application\Port\Inbound\InterventionWorkloadContributionsPort;
use Organization\Application\Port\Inbound\OrganizationWorkforceDirectoryPort;
use Shared\Application\Port\Outbound\ClockPort;
use Shared\Domain\Exception\InvalidValueException;
use Workload\Application\Contract\Capacity\{CapacityExceptionView, CapacityWeekView};
use Workload\Application\Contract\Projection\{MemberWorkloadView, UnallocatedWorkView, WorkloadDayView, WorkloadProjectionSnapshot, WorkloadProjectionView};
use Workload\Application\Port\Inbound\WorkloadProjectionPort;
use Workload\Application\Port\Outbound\CapacityRepositoryPort;
use Workload\Domain\Model\Capacity\CapacitySchedule;
use Workload\Domain\Service\WorkloadAllocationPolicy;
use Workload\Domain\ValueObject\{CapacityChange, CapacityException, CapacityWeek, DailyWorkload, LocalDate, WorkDemand};

use function array_filter;
use function array_keys;
use function array_map;
use function array_values;
use function hash;
use function in_array;
use function iterator_to_array;
use function json_encode;
use function ksort;

use const JSON_THROW_ON_ERROR;

/**
 * Service WorkloadProjector.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class WorkloadProjector implements WorkloadProjectionPort
{
  /**
   * @since 1.0.0
   *
   * @param InterventionWorkloadContributionsPort $contributions reads task demand and actual time through Intervention contracts
   * @param OrganizationWorkforceDirectoryPort $workforce organization-local membership and regional context directory
   * @param CapacityRepositoryPort $capacities reads historical weeks and dated availability exceptions
   * @param ClockPort $clock clock used for calculation and journal timestamps
   */
  public function __construct(
    private InterventionWorkloadContributionsPort $contributions,
    private OrganizationWorkforceDirectoryPort $workforce,
    private CapacityRepositoryPort $capacities,
    private ClockPort $clock,
  ) {
  }

  /**
   * Projects daily actual and remaining demand while keeping drafts and unknown work explicit.
   *
   * @since 1.0.0
   *
   * @param string $organizationId organization identifier that scopes this operation
   * @param string $from inclusive first local date in the requested period
   * @param string $to inclusive last local date in the requested period
   * @param ?list<string> $memberIds
   * @param list<InterventionWorkContribution> $replacements
   *
   * @return WorkloadProjectionSnapshot daily projection and fingerprint of the relevant input data
   */
  public function project(string $organizationId, string $from, string $to, ?array $memberIds = null, array $replacements = []): WorkloadProjectionSnapshot
  {
    $start = LocalDate::fromString($from);
    $end = LocalDate::fromString($to);
    if ($from > $to) {
      throw InvalidValueException::because('The workload period is reversed.');
    }
    $context = $this->workforce->context($organizationId);
    if (null === $context) {
      throw InvalidValueException::because('Organization context is unavailable.');
    }
    $now = $this->clock->now();
    $today = LocalDate::fromString($now->setTimezone(new DateTimeZone($context->timezone))->format('Y-m-d'));
    $weeks = $this->capacities->weeks($organizationId);
    $exceptions = $this->capacities->exceptions($organizationId);
    $tasks = $this->tasksForProjection($organizationId, $context->timezone, $replacements);
    $actuals = $this->contributions->actuals($organizationId, $from, $to);
    $members = $this->selectedMembers($organizationId, $memberIds, $tasks, $actuals);
    $organizationWeeks = array_values(array_filter($weeks, static fn ($week): bool => $week->scopeId === $organizationId));
    $toChange = static fn (CapacityWeekView $week): CapacityChange => new CapacityChange(LocalDate::fromString($week->effectiveOn), new CapacityWeek($week->minutes));
    // Index complete source reads once. Member pagination remains outside this projection.
    $weeksByScope = self::weeksByScope($weeks);
    $exceptionsByMember = self::exceptionsByMember($exceptions);
    $tasksByMember = self::tasksByMember($tasks);
    $actualsByMember = self::actualsByMember($actuals);
    $organizationChanges = array_map($toChange, $organizationWeeks);
    $dates = iterator_to_array(self::dates($start, $end), false);
    $output = [];
    $overall = 'complete';
    $hasKnownCapacity = false;
    foreach (array_keys($members) as $memberId) {
      $schedule = new CapacitySchedule(
        $organizationChanges,
        array_map($toChange, $weeksByScope[$memberId] ?? []),
        array_map(
          static fn ($exception): CapacityException => new CapacityException(LocalDate::fromString($exception->startsOn), LocalDate::fromString($exception->endsOn), $exception->minutes),
          $exceptionsByMember[$memberId] ?? [],
        ),
      );
      [$shares, $unallocated] = self::taskShares($tasksByMember[$memberId] ?? [], $actualsByMember[$memberId] ?? [], $schedule, $today, $from, $to);
      [$days, $memberHasKnownCapacity, $memberComplete] = self::dayViews($dates, $shares, $schedule, $today, [] !== $unallocated);
      $hasKnownCapacity = $hasKnownCapacity || $memberHasKnownCapacity;
      if (!$memberComplete) {
        $overall = 'partial';
      }
      $output[] = new MemberWorkloadView($memberId, $days, $unallocated);
    }
    $unassigned = self::unassignedTasks($tasks, $memberIds);
    $scopedTasks = array_values(array_filter($tasks, static fn ($task): bool => null === $memberIds || in_array($task->memberId, $memberIds, true)));
    $scopedActuals = array_values(array_filter($actuals, static fn ($entry): bool => null === $memberIds || in_array($entry->memberId, $memberIds, true)));
    $fingerprint = hash('sha256', json_encode([$scopedTasks, $scopedActuals, $weeks, $exceptions, $today->value], JSON_THROW_ON_ERROR));
    $completeness = $overall;
    if (!$hasKnownCapacity) {
      $completeness = 'unavailable';
    } elseif ([] !== $unassigned) {
      $completeness = 'partial';
    }

    return new WorkloadProjectionSnapshot(new WorkloadProjectionView(
      $from,
      $to,
      $today->value,
      $context->timezone,
      $context->firstDayOfWeek,
      $now->format('c'),
      $output,
      $unassigned,
      $completeness,
    ), $fingerprint);
  }

  /**
   * @since 1.0.0
   *
   * @param list<InterventionWorkContribution> $replacements proposed task overrides
   *
   * @return array<string, InterventionWorkContribution> tasks keyed and ordered by task id
   */
  private function tasksForProjection(string $organizationId, string $timezone, array $replacements): array
  {
    $tasks = [];
    foreach ($this->contributions->tasks($organizationId, $timezone) as $task) {
      $tasks[$task->taskId] = $task;
    }
    foreach ($replacements as $replacement) {
      $tasks[$replacement->taskId] = $replacement;
    }
    ksort($tasks);

    return $tasks;
  }

  /**
   * @since 1.0.0
   *
   * @param list<CapacityWeekView> $weeks weekly capacity changes
   *
   * @return array<string, list<CapacityWeekView>> changes by scope
   */
  private static function weeksByScope(array $weeks): array
  {
    $indexed = [];
    foreach ($weeks as $week) {
      $indexed[$week->scopeId][] = $week;
    }

    return $indexed;
  }

  /**
   * @since 1.0.0
   *
   * @param list<CapacityExceptionView> $exceptions dated capacity exceptions
   *
   * @return array<string, list<CapacityExceptionView>> exceptions by member
   */
  private static function exceptionsByMember(array $exceptions): array
  {
    $indexed = [];
    foreach ($exceptions as $exception) {
      $indexed[$exception->memberId][] = $exception;
    }

    return $indexed;
  }

  /**
   * @since 1.0.0
   *
   * @param array<string, InterventionWorkContribution> $tasks task contributions
   *
   * @return array<string, list<InterventionWorkContribution>> tasks by assigned member
   */
  private static function tasksByMember(array $tasks): array
  {
    $indexed = [];
    foreach ($tasks as $task) {
      if (null !== $task->memberId) {
        $indexed[$task->memberId][] = $task;
      }
    }

    return $indexed;
  }

  /**
   * @since 1.0.0
   *
   * @param list<InterventionTimeContribution> $actuals recorded time contributions
   *
   * @return array<string, list<InterventionTimeContribution>> records by member
   */
  private static function actualsByMember(array $actuals): array
  {
    $indexed = [];
    foreach ($actuals as $entry) {
      $indexed[$entry->memberId][] = $entry;
    }

    return $indexed;
  }

  /**
   * Keep future remaining demand separate from factual time contributions.
   *
   * @since 1.0.0
   *
   * @param list<InterventionWorkContribution> $tasks
   * @param list<InterventionTimeContribution> $actuals
   *
   * @return array{array<string, list<array{taskId: string, kind: string, minutes: int, entryId: ?string, interventionId: ?string, label: ?string}>>, list<UnallocatedWorkView>}
   */
  private static function taskShares(array $tasks, array $actuals, CapacitySchedule $schedule, LocalDate $today, string $from, string $to): array
  {
    $shares = [];
    $unallocated = [];
    $policy = new WorkloadAllocationPolicy();
    foreach ($tasks as $task) {
      if (null !== $task->startsOn && $task->startsOn > $to) {
        continue;
      }
      $allocation = $policy->allocate(new WorkDemand(
        $task->taskId,
        $task->memberId,
        $task->remainingMinutes,
        null === $task->startsOn ? null : LocalDate::fromString($task->startsOn),
        null === $task->endsOn ? null : LocalDate::fromString($task->endsOn),
        $task->commitment,
      ), $schedule, $today);
      if (null !== $allocation->unallocatedReason) {
        $unallocated[] = self::unallocated($task, $allocation->unallocatedReason);
      }
      self::appendDailyShares($shares, $task, $allocation->dailyMinutes, $from, $to);
    }
    foreach ($actuals as $entry) {
      $shares[$entry->workedOn][] = ['taskId' => $entry->taskId, 'kind' => 'actual', 'minutes' => $entry->minutes, 'entryId' => $entry->entryId, 'interventionId' => $entry->interventionId, 'label' => $entry->label];
    }

    return [$shares, $unallocated];
  }

  /**
   * @since 1.0.0
   *
   * @param array<string, list<array{taskId: string, kind: string, minutes: int, entryId: ?string, interventionId: ?string, label: ?string}>> $shares
   * @param InterventionWorkContribution $task the allocated task
   * @param array<string, int> $dailyMinutes allocation over the full task period
   * @param string $from inclusive first visible date
   * @param string $to inclusive last visible date
   */
  private static function appendDailyShares(array &$shares, InterventionWorkContribution $task, array $dailyMinutes, string $from, string $to): void
  {
    foreach ($dailyMinutes as $date => $minutes) {
      if ($date >= $from && $date <= $to && $minutes > 0) {
        $shares[$date][] = ['taskId' => $task->taskId, 'kind' => $task->commitment, 'minutes' => $minutes, 'entryId' => null, 'interventionId' => $task->interventionId, 'label' => $task->label];
      }
    }
  }

  /**
   * Materialize each local day from the complete, indexed contribution set.
   *
   * @since 1.0.0
   *
   * @param list<LocalDate> $dates
   * @param array<string, list<array{taskId: string, kind: string, minutes: int, entryId: ?string, interventionId: ?string, label: ?string}>> $shares
   *
   * @return array{list<WorkloadDayView>, bool, bool} day views, known-capacity flag, completeness flag
   */
  private static function dayViews(array $dates, array $shares, CapacitySchedule $schedule, LocalDate $today, bool $hasUnallocated): array
  {
    $days = [];
    $hasKnownCapacity = false;
    $complete = true;
    foreach ($dates as $date) {
      $totals = ['actual' => 0, 'committed' => 0, 'draft' => 0];
      foreach ($shares[$date->value] ?? [] as $share) {
        $totals[$share['kind']] += $share['minutes'];
      }
      $capacity = $schedule->on($date);
      $hasKnownCapacity = $hasKnownCapacity || null !== $capacity;
      $daily = new DailyWorkload($capacity, $totals['actual'], $totals['committed'], $totals['draft'], $date->value >= $today->value && $hasUnallocated);
      if ('complete' !== $daily->completeness()) {
        $complete = false;
      }
      $days[] = new WorkloadDayView(
        $date->value,
        $daily->capacityMinutes,
        $daily->actualMinutes,
        $daily->remainingMinutes,
        $daily->draftMinutes,
        $daily->overloadMinutes(),
        $daily->utilizationPercent(),
        $daily->completeness(),
        $daily->availability(),
        $shares[$date->value] ?? [],
      );
    }

    return [$days, $hasKnownCapacity, $complete];
  }

  /**
   * Include active members and contributors retained in the projection history.
   *
   * @since 1.0.0
   *
   * @param ?list<string> $memberIds
   * @param array<string, InterventionWorkContribution> $tasks
   * @param list<InterventionTimeContribution> $actuals
   *
   * @return array<string, true>
   */
  private function selectedMembers(string $organizationId, ?array $memberIds, array $tasks, array $actuals): array
  {
    $members = [];
    foreach ($this->workforce->members($organizationId) as $member) {
      if ($member->active && (null === $memberIds || in_array($member->id, $memberIds, true))) {
        $members[$member->id] = true;
      }
    }
    foreach ($tasks as $task) {
      if (null !== $task->memberId && (null === $memberIds || in_array($task->memberId, $memberIds, true))) {
        $members[$task->memberId] = true;
      }
    }
    foreach ($actuals as $entry) {
      if (null === $memberIds || in_array($entry->memberId, $memberIds, true)) {
        $members[$entry->memberId] = true;
      }
    }
    ksort($members);

    return $members;
  }

  /**
   * @since 1.0.0
   *
   * @param array<string, InterventionWorkContribution> $tasks
   * @param ?list<string> $memberIds
   *
   * @return list<UnallocatedWorkView>
   */
  private static function unassignedTasks(array $tasks, ?array $memberIds): array
  {
    if (null !== $memberIds) {
      return [];
    }
    $unassigned = [];
    foreach ($tasks as $task) {
      if (null === $task->memberId && 'none' !== $task->commitment && 0 !== $task->remainingMinutes) {
        $unassigned[] = self::unallocated($task, 'unassigned');
      }
    }

    return $unassigned;
  }

  /**
   * Enumerates the inclusive local-date window.
   *
   * @since 1.0.0
   *
   * @param LocalDate $from inclusive first local date in the requested period
   * @param LocalDate $to inclusive last local date in the requested period
   *
   * @return iterable<LocalDate>
   */
  private static function dates(LocalDate $from, LocalDate $to): iterable
  {
    for ($date = $from; $date->value <= $to->value; $date = $date->next()) {
      yield $date;
    }
  }

  /**
   * Preserves a task and the explicit reason it could not be distributed.
   *
   * @since 1.0.0
   *
   * @param InterventionWorkContribution $task task contribution or authorization context for this operation
   * @param string $reason reason the task cannot be included in a complete daily allocation
   *
   * @return UnallocatedWorkView task contribution with its explicit allocation failure reason
   */
  private static function unallocated(InterventionWorkContribution $task, string $reason): UnallocatedWorkView
  {
    return new UnallocatedWorkView(
      $task->taskId,
      $task->interventionId,
      $task->label,
      $task->memberId,
      $task->remainingMinutes,
      $task->startsOn,
      $task->endsOn,
      $task->commitment,
      $reason,
    );
  }
}
