<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Time;

/**
 * TimeEntryTaskContext.
 *
 * @category Intervention
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TimeEntryTaskContext
{
  /**
   * @since 1.0.0
   *
   * @param string $taskId intervention work-item identifier
   * @param string $interventionId owning intervention identifier
   * @param string $organizationId organization identifier that scopes this operation
   * @param ?string $assigneeId current task assignee, or null when unassigned
   * @param ?string $responsibleId responsible intervention member, independent of task assignment
   * @param list<string> $participants
   * @param list<string> $previousAssignees
   */
  public function __construct(
    public string $taskId,
    public string $interventionId,
    public string $organizationId,
    public ?string $assigneeId,
    public ?string $responsibleId,
    public array $participants,
    public array $previousAssignees,
  ) {
  }
}
