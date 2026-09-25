<?php

declare(strict_types=1);

namespace Intervention\Domain\Model\Intervention;

use Intervention\Domain\ValueObject\InterventionType;

/** Data supplied to create or restore an intervention. */
final readonly class InterventionCreation
{
  public function __construct(
    public string $id,
    public string $organizationId,
    public InterventionType $type,
    public InterventionContent $content,
    public InterventionOwnership $ownership,
    public InterventionSchedule $schedule,
  ) {
  }
}
