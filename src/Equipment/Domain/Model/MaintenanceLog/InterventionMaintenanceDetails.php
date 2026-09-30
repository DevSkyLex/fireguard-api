<?php

declare(strict_types=1);

namespace Equipment\Domain\Model\MaintenanceLog;

/** The source intervention that produced a completed service entry. */
final readonly class InterventionMaintenanceDetails
{
  /**
   * Method __construct
   *
   * Captures the intervention and work-item details that produced a maintenance log entry.
   *
   * @access public
   *
   * @param string $interventionId identifier of the source intervention
   * @param int $interventionNumber display number of the source intervention
   * @param string $workItemAction action recorded by the completed work item
   * @param ?string $actorId user or system actor who completed the work, when known
   * @param ?string $summary optional summary of the completed work
   *
   * @return void
   */
  public function __construct(
    public string $interventionId,
    public int $interventionNumber,
    public string $workItemAction,
    public ?string $actorId,
    public ?string $summary = null,
  ) {
  }
}
