<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Command\Equipment\AddAttachment;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase AddAttachmentCommand.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AddAttachmentCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Carries the organization-scoped equipment attachment and its file metadata for storage.
   *
   * @access public
   *
   * @param string $organizationId organization scope used to resolve the equipment
   * @param string $equipmentId equipment receiving the attachment
   * @param string $fileName client file name used to label the stored attachment
   * @param string $contents file bytes written to the storage port
   * @param string $mimeType declared media type of the file
   * @param int $size file size in bytes
   * @param ?string $label optional user-facing attachment label
   * @param ?string $attachmentId stable identifier supplied when retrying an upload, when available
   *
   * @return void
   */
  public function __construct(
    public string $organizationId,
    public string $equipmentId,
    public string $fileName,
    public string $contents,
    public string $mimeType,
    public int $size,
    public ?string $label = null,
    public ?string $attachmentId = null,
  ) {
  }
  // #endregion
}
