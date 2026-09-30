<?php

declare(strict_types=1);

namespace Messaging\Domain\Model\Attachment;

/**
 * File metadata carried by an attachment, independently of its owner.
 */
final readonly class MessagingAttachmentFile
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Captures file metadata independently of the owning message or conversation.
   *
   * @access public
   *
   * @param string $fileName original file name
   * @param string $storagePath path used by the storage adapter
   * @param string $mimeType declared media type
   * @param int $size file size in bytes
   * @param ?string $label optional display label
   *
   * @return void
   */
  public function __construct(
    public string $fileName,
    public string $storagePath,
    public string $mimeType,
    public int $size,
    public ?string $label = null,
  ) {
  }
  // #endregion
}
