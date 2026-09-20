<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Workload;

/**
 * Contract InterventionTimeContribution.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionTimeContribution
{
  /**
   * @since 1.0.0
   *
   * @param string $entryId independent time-entry identifier
   * @param string $taskId intervention work-item identifier
   * @param string $memberId organization member whose work or capacity is represented
   * @param string $workedOn organization-local calendar date on which the work was performed
   * @param int $minutes duration in whole minutes
   * @param int $revision revision of this resource, used independently of other aggregates
   * @param ?string $interventionId owning intervention identifier
   * @param ?string $label human-readable task label for the contribution
   */
  public function __construct(
    public string $entryId,
    public string $taskId,
    public string $memberId,
    public string $workedOn,
    public int $minutes,
    public int $revision,
    public ?string $interventionId = null,
    public ?string $label = null,
  ) {
  }
}
