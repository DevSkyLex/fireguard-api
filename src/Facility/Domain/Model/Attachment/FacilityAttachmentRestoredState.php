<?php

declare(strict_types=1);

namespace Facility\Domain\Model\Attachment;

use DateTimeImmutable;

/** Timestamp and presentation state restored from a persisted attachment. */
final readonly class FacilityAttachmentRestoredState
{
  public function __construct(
    public DateTimeImmutable $uploadedAt,
    public ?FacilityAttachmentCreationOptions $options = null,
    public bool $isPrimaryPlan = false,
  ) {
  }
}
