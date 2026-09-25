<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Recurrence;

/**
 * The validated identity and schedule to persist for a recurrence.
 */
final readonly class InterventionRecurrenceCreateRequest
{
  public function __construct(
    public string $organizationId,
    public string $templateId,
    public string $name,
    public ?string $siteId,
    public ?string $responsibleId,
    public InterventionRecurrenceSchedule $schedule,
  ) {
  }
}
