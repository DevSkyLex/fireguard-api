<?php

declare(strict_types=1);

namespace Facility\Domain\Model\Attachment;

use Facility\Domain\ValueObject\AttachmentKind;

/** Optional attachment details supplied when an upload is created. */
final readonly class FacilityAttachmentCreationOptions
{
  public function __construct(
    public ?string $label = null,
    public AttachmentKind $kind = AttachmentKind::DOCUMENT,
    public ?int $imageWidth = null,
    public ?int $imageHeight = null,
  ) {
  }
}
