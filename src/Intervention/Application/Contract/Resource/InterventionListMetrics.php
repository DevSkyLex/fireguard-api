<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Resource;

/**
 * Resource InterventionListMetrics.
 *
 * @category Resource
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionListMetrics
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param int $facilities the facility count
   * @param int $equipment the equipment count
   * @param int $inspections the inspection count
   * @param int $workItems the work item count
   * @param int $completedWorkItems the completed work item count
   * @param int $requiredIncomplete the required incomplete work item count
   * @param int $proposedChanges the proposed change count
   * @param int $resourceBlockers the resource blocker count
   * @param int $commentsCount the grouped comment count
   * @param bool $hasSignature whether a completion signature exists
   * @param list<array{id: string, name: string, color: string}> $labels the grouped display labels
   */
  public function __construct(
    public int $facilities = 0,
    public int $equipment = 0,
    public int $inspections = 0,
    public int $workItems = 0,
    public int $completedWorkItems = 0,
    public int $requiredIncomplete = 0,
    public int $proposedChanges = 0,
    public int $resourceBlockers = 0,
    public int $commentsCount = 0,
    public bool $hasSignature = false,
    public array $labels = [],
  ) {
  }
}
