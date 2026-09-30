<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Attachment\AddInspectionAttachment;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase AddInspectionAttachmentResult.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AddInspectionAttachmentResult implements ResultMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Returns the stored inspection attachment identity, file metadata, optional association, and upload time.
   *
   * @access public
   *
   * @param string $attachmentId identifier assigned to the stored attachment
   * @param string $inspectionId inspection that owns the attachment
   * @param string $fileName stored file name
   * @param string $mimeType stored media type
   * @param int $size file size in bytes
   * @param ?string $nonConformityId related non-conformity identifier, when present
   * @param ?string $label optional saved user-facing label
   * @param DateTimeImmutable $uploadedAt time the attachment was recorded as uploaded
   *
   * @return void
   */
  public function __construct(
    public string $attachmentId,
    public string $inspectionId,
    public string $fileName,
    public string $mimeType,
    public int $size,
    public ?string $nonConformityId,
    public ?string $label,
    public DateTimeImmutable $uploadedAt,
  ) {
  }
  // #endregion
}
