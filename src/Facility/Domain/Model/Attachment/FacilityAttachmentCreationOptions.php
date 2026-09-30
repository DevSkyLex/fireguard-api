<?php

declare(strict_types=1);

namespace Facility\Domain\Model\Attachment;

use Facility\Domain\ValueObject\AttachmentKind;

/** Optional attachment details supplied when an upload is created. */
final readonly class FacilityAttachmentCreationOptions
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Groups the optional label, attachment kind, and image dimensions supplied at attachment creation.
   *
   * @access public
   *
   * @param ?string $label optional display label for the attachment
   * @param AttachmentKind $kind classification used to distinguish documents and plan images
   * @param ?int $imageWidth image width in pixels, when known
   * @param ?int $imageHeight image height in pixels, when known
   *
   * @return void
   */
  public function __construct(
    public ?string $label = null,
    public AttachmentKind $kind = AttachmentKind::DOCUMENT,
    public ?int $imageWidth = null,
    public ?int $imageHeight = null,
  ) {
  }
  // #endregion
}
