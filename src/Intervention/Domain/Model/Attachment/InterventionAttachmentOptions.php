<?php

declare(strict_types=1);

namespace Intervention\Domain\Model\Attachment;

use Intervention\Domain\ValueObject\InterventionAttachmentKind;

/** Optional attachment ownership, label and kind. */
final readonly class InterventionAttachmentOptions
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Captures optional label, work-item ownership and attachment kind.
   *
   * @access public
   *
   * @param ?string $label optional display label
   * @param ?string $workItemId optional linked work-item identifier
   * @param InterventionAttachmentKind $kind attachment kind, defaulting to a regular file
   *
   * @return void
   */
  public function __construct(
    public ?string $label = null,
    public ?string $workItemId = null,
    public InterventionAttachmentKind $kind = InterventionAttachmentKind::FILE,
  ) {
  }
  // #endregion
}
