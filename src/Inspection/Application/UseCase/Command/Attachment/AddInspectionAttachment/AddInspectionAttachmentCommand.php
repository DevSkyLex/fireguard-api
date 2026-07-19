<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Attachment\AddInspectionAttachment;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase AddInspectionAttachmentCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AddInspectionAttachmentCommand implements CommandMessage
{
  // #region Constructor
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
