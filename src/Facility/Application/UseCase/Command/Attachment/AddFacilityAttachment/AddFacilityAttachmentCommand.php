<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\Attachment\AddFacilityAttachment;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase AddFacilityAttachmentCommand.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AddFacilityAttachmentCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Carries an organization-scoped facility file and its metadata for storage.
   *
   * @access public
   *
   * @param string $organizationId organization scope used to validate the facility
   * @param string $facilityId facility receiving the attachment
   * @param string $fileName client file name used for the stored attachment
   * @param string $contents file bytes written to storage
   * @param string $mimeType declared media type of the file
   * @param int $size file size in bytes
   * @param ?string $label optional user-facing attachment label
   * @param ?string $attachmentId stable attachment identifier supplied for a retry, when available
   * @param string $kind attachment classification, defaulting to a document
   *
   * @return void
   */
  public function __construct(
    public string $organizationId,
    public string $facilityId,
    public string $fileName,
    public string $contents,
    public string $mimeType,
    public int $size,
    public ?string $label = null,
    public ?string $attachmentId = null,
    public string $kind = 'document',
  ) {
  }
  // #endregion
}
