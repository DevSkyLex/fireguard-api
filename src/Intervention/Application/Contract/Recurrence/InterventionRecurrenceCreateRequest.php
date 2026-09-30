<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Recurrence;

/**
 * The validated identity and schedule to persist for a recurrence.
 */
final readonly class InterventionRecurrenceCreateRequest
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Carries the validated identity, ownership and schedule for a recurrence.
   *
   * @access public
   *
   * @param string $organizationId organization that owns the recurrence
   * @param string $templateId template used to create each occurrence
   * @param string $name display name of the recurrence
   * @param ?string $siteId optional default site for generated interventions
   * @param ?string $responsibleId optional responsible member for generated interventions
   * @param InterventionRecurrenceSchedule $schedule validated cadence and timing configuration
   *
   * @return void
   */
  public function __construct(
    public string $organizationId,
    public string $templateId,
    public string $name,
    public ?string $siteId,
    public ?string $responsibleId,
    public InterventionRecurrenceSchedule $schedule,
  ) {
  }
  // #endregion
}
