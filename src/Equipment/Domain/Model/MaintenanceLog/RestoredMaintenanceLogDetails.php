<?php

declare(strict_types=1);

namespace Equipment\Domain\Model\MaintenanceLog;

use Equipment\Domain\ValueObject\MaintenanceLogSource;

/** The independently nullable source fields loaded from an existing log. */
final readonly class RestoredMaintenanceLogDetails
{
  /**
   * Method __construct
   *
   * Restores the independently optional source, intervention, actor, and summary fields of a maintenance log.
   *
   * @access public
   *
   * @param MaintenanceLogSource $source origin of the maintenance log entry
   * @param ?string $interventionId source intervention identifier, when linked
   * @param ?int $interventionNumber source intervention number, when linked
   * @param ?string $workItemAction completed work-item action, when available
   * @param ?string $actorId actor recorded for the entry, when available
   * @param ?string $summary stored summary, when available
   *
   * @return void
   */
  public function __construct(
    public MaintenanceLogSource $source = MaintenanceLogSource::STATUS_TRANSITION,
    public ?string $interventionId = null,
    public ?int $interventionNumber = null,
    public ?string $workItemAction = null,
    public ?string $actorId = null,
    public ?string $summary = null,
  ) {
  }
}
