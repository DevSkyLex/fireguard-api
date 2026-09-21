<?php

declare(strict_types=1);

namespace Facility\Application\Contract\Event;

use DateTimeImmutable;

/** Event FacilityPlanGeometryChangedEvent. Committed spatial change; coordinates and free text stay out of the audit feed. */
final readonly class FacilityPlanGeometryChangedEvent
{
  public function __construct(
    public string $organizationId,
    public string $resourceId,
    public ?string $previousAttachmentId,
    public ?string $attachmentId,
    public int $revision,
    public ?string $interventionId,
    public DateTimeImmutable $occurredAt,
  ) {
  }
}
