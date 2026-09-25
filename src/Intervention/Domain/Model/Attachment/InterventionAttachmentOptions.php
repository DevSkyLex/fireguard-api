<?php

declare(strict_types=1);

namespace Intervention\Domain\Model\Attachment;

use Intervention\Domain\ValueObject\InterventionAttachmentKind;

/** Optional attachment ownership, label and kind. */
final readonly class InterventionAttachmentOptions
{
  public function __construct(
    public ?string $label = null,
    public ?string $workItemId = null,
    public InterventionAttachmentKind $kind = InterventionAttachmentKind::FILE,
  ) {
  }
}
