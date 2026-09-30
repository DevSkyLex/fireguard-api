<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Command\Equipment\AddAttachment;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase AddAttachmentResult.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AddAttachmentResult implements ResultMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Returns the persisted attachment identity, file metadata, and upload time.
   *
   * @access public
   *
   * @param string $attachmentId identifier assigned to the stored attachment
   * @param string $equipmentId equipment that owns the attachment
   * @param string $fileName stored attachment file name
   * @param string $mimeType stored media type
   * @param int $size stored file size in bytes
   * @param ?string $label optional label saved with the attachment
   * @param DateTimeImmutable $uploadedAt time the attachment was recorded as uploaded
   *
   * @return void
   */
  public function __construct(
    public string $attachmentId,
    public string $equipmentId,
    public string $fileName,
    public string $mimeType,
    public int $size,
    public ?string $label,
    public DateTimeImmutable $uploadedAt,
  ) {
  }
  // #endregion
}
