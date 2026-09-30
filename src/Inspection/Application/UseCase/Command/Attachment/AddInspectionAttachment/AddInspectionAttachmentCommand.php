<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Attachment\AddInspectionAttachment;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase AddInspectionAttachmentCommand.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AddInspectionAttachmentCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Carries an inspection attachment and optional non-conformity association for storage.
   *
   * @access public
   *
   * @param string $organizationId organization scope used to validate the inspection
   * @param string $inspectionId inspection receiving the attachment
   * @param string $fileName client file name retained for the attachment
   * @param string $contents file bytes written to storage
   * @param string $mimeType declared media type of the file
   * @param int $size file size in bytes
   * @param ?string $nonConformityId related non-conformity identifier, when the file documents one
   * @param ?string $label optional user-facing attachment label
   * @param ?string $attachmentId stable attachment identifier supplied for a retry, when available
   *
   * @return void
   */
  public function __construct(
    public string $organizationId,
    public string $inspectionId,
    public string $fileName,
    public string $contents,
    public string $mimeType,
    public int $size,
    public ?string $nonConformityId = null,
    public ?string $label = null,
    public ?string $attachmentId = null,
  ) {
  }
  // #endregion
}
