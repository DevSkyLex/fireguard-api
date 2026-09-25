<?php

declare(strict_types=1);

namespace Workload\Application\UseCase\Query\Planning\AssessWorkload;

use Intervention\Application\Contract\Workload\InterventionWorkContribution;
use Intervention\Application\Port\Inbound\InterventionWorkloadContributionsPort;
use Organization\Application\Port\Inbound\{OrganizationAuthorizationPort, OrganizationWorkforceDirectoryPort};
use Shared\Application\Message\QueryHandler;
use Shared\Domain\Exception\InvalidValueException;
use Workload\Application\Contract\Planning\WorkloadTaskProposal;
use Workload\Application\Port\Inbound\WorkloadPlanningPort;
use Workload\Domain\Exception\{WorkloadAccessDeniedException, WorkloadNotFoundException};
use Workload\Domain\ValueObject\{LocalDate, WorkDemand};

use function array_map;
use function array_values;
use function in_array;

/**
 * AssessWorkloadHandler.
 * Simulation only: subsequent writes re-evaluate under workload locks.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AssessWorkloadHandler implements QueryHandler
{
  /**
   * @since 1.0.0
   *
   * @param OrganizationAuthorizationPort $authorization organization membership and permission checks
   * @param OrganizationWorkforceDirectoryPort $workforce organization-local membership and regional context directory
   * @param InterventionWorkloadContributionsPort $contributions reads task demand and actual time through Intervention contracts
   * @param WorkloadPlanningPort $planning simulates workload changes and validates explicit consent
   */
  public function __construct(
    private OrganizationAuthorizationPort $authorization,
    private OrganizationWorkforceDirectoryPort $workforce,
    private InterventionWorkloadContributionsPort $contributions,
    private WorkloadPlanningPort $planning,
  ) {
  }

  /**
   * Simulates authorized task replacements or the complete planning of a draft.
   *
   * @since 1.0.0
   *
   * @param AssessWorkloadQuery $query requested read scope and caller context
   *
   * @return AssessWorkloadResult read-only daily impact of the proposed changes
   */
  public function __invoke(AssessWorkloadQuery $query): AssessWorkloadResult
  {
    if (!$this->authorization->isMemberOf($query->userId, $query->organizationId)) {
      throw new WorkloadNotFoundException('Organization not found.');
    }
    if (!$this->authorization->hasPermission($query->userId, $query->organizationId, 'organization.interventions.plan')) {
      throw new WorkloadAccessDeniedException('Planning permission is required.');
    }
    $context = $this->workforce->context($query->organizationId);
    if (null === $context) {
      throw new WorkloadNotFoundException('Organization not found.');
    }
    $activeMembers = [];
    foreach ($this->workforce->members($query->organizationId) as $member) {
      if ($member->active) {
        $activeMembers[] = $member->id;
      }
    }
    [$tasks, $replacements, $members] = $this->loadContributions($query, $context->timezone);
    [$replacements, $members] = self::applyChanges($query->changes, $tasks, $replacements, $members, $activeMembers);
    // Match the writer's parent-scoped coordination and confirmation scope.
    $parents = array_map(static fn (InterventionWorkContribution $task): string => $task->interventionId, $replacements);
    foreach ($tasks as $task) {
      if (null !== $task->memberId && in_array($task->interventionId, $parents, true)) {
        $members[] = $task->memberId;
      }
    }
    $before = $this->planning->capture($query->organizationId, $members);

    return new AssessWorkloadResult($this->planning->assess($before, array_values($replacements)));
  }

  /**
   * @since 1.0.0
   *
   * @return array{array<string, InterventionWorkContribution>, array<string, InterventionWorkContribution>, list<string>}
   */
  private function loadContributions(AssessWorkloadQuery $query, string $timezone): array
  {
    $tasks = [];
    $replacements = [];
    $members = [];
    $foundDraft = null === $query->planInterventionId;
    foreach ($this->contributions->tasks($query->organizationId, $timezone) as $task) {
      $tasks[$task->taskId] = $task;
      if ($task->interventionId === $query->planInterventionId && 'draft' === $task->commitment) {
        $foundDraft = true;
        $replacements[$task->taskId] = new InterventionWorkContribution(
          $task->taskId,
          $task->interventionId,
          $task->label,
          $task->memberId,
          $task->remainingMinutes,
          $task->startsOn,
          $task->endsOn,
          'committed',
          $task->revision,
        );
        if (null !== $task->memberId) {
          $members[] = $task->memberId;
        }
      }
    }
    if (!$foundDraft) {
      throw new WorkloadNotFoundException('Draft intervention not found.');
    }

    return [$tasks, $replacements, $members];
  }

  /**
   * @since 1.0.0
   *
   * @param list<WorkloadTaskProposal> $changes
   * @param array<string, InterventionWorkContribution> $tasks
   * @param array<string, InterventionWorkContribution> $replacements
   * @param list<string> $members
   * @param list<string> $activeMembers
   *
   * @return array{array<string, InterventionWorkContribution>, list<string>}
   */
  private static function applyChanges(array $changes, array $tasks, array $replacements, array $members, array $activeMembers): array
  {
    foreach ($changes as $change) {
      $task = $tasks[$change->taskId] ?? null;
      if (null === $task) {
        throw new WorkloadNotFoundException('Editable work item not found.');
      }
      $commitment = $replacements[$task->taskId]->commitment ?? $task->commitment;
      $replacements[$task->taskId] = self::replacementFor($change, $task, $activeMembers, $commitment);
      if (null !== $task->memberId) {
        $members[] = $task->memberId;
      }
      if (null !== $change->memberId) {
        $members[] = $change->memberId;
      }
    }

    return [$replacements, $members];
  }

  /**
   * Validates and projects a single proposed task replacement.
   *
   * @since 1.0.0
   *
   * @param WorkloadTaskProposal $change the proposed task edit
   * @param InterventionWorkContribution $task the existing task contribution
   * @param list<string> $activeMembers members eligible for assignment
   * @param string $commitment the effective commitment after draft planning
   *
   * @return InterventionWorkContribution the validated replacement
   */
  private static function replacementFor(WorkloadTaskProposal $change, InterventionWorkContribution $task, array $activeMembers, string $commitment): InterventionWorkContribution
  {
    if (null !== $change->memberId && !in_array($change->memberId, $activeMembers, true)) {
      throw new WorkloadNotFoundException('Active member not found.');
    }
    if ((null === $change->startsOn) !== (null === $change->endsOn)) {
      throw InvalidValueException::because('Both task period dates are required.');
    }
    new WorkDemand(
      $task->taskId,
      $change->memberId,
      $change->remainingMinutes,
      null === $change->startsOn ? null : LocalDate::fromString($change->startsOn),
      null === $change->endsOn ? null : LocalDate::fromString($change->endsOn),
      $commitment,
    );

    return new InterventionWorkContribution(
      $task->taskId,
      $task->interventionId,
      $task->label,
      $change->memberId,
      $change->remainingMinutes,
      $change->startsOn,
      $change->endsOn,
      $commitment,
      $task->revision,
    );
  }
}
