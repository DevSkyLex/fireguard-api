<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Attachment\DeleteInspectionAttachment;

use Inspection\Application\Port\Outbound\{InspectionAttachmentRepositoryPort, InspectionRepositoryPort};
use Inspection\Domain\Exception\{InspectionAttachmentNotFoundException, InspectionNotFoundException};
use Inspection\Domain\ValueObject\{InspectionAttachmentId, InspectionId, InspectionOrganizationId};
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\FileStoragePort;

/**
 * UseCase DeleteInspectionAttachmentHandler.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteInspectionAttachmentHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Receives inspection and attachment repositories plus file storage to remove the attachment metadata and bytes.
   *
   * @access public
   *
   * @param InspectionRepositoryPort $inspectionRepository port used to validate the inspection and organization scope
   * @param InspectionAttachmentRepositoryPort $attachmentRepository port used to load and remove attachment metadata
   * @param FileStoragePort $fileStorage port used to delete the attachment bytes
   *
   * @return void
   */
  public function __construct(
    private InspectionRepositoryPort $inspectionRepository,
    private InspectionAttachmentRepositoryPort $attachmentRepository,
    private FileStoragePort $fileStorage,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   */
  public function __invoke(DeleteInspectionAttachmentCommand $command): DeleteInspectionAttachmentResult
  {
    $inspectionId = InspectionId::fromString($command->inspectionId);
    $organizationId = InspectionOrganizationId::fromString($command->organizationId);
    $attachmentId = InspectionAttachmentId::fromString($command->attachmentId);

    $inspection = $this->inspectionRepository->findById($inspectionId);

    if (null === $inspection || (string) $inspection->organizationId() !== (string) $organizationId) {
      throw InspectionNotFoundException::withId($command->inspectionId);
    }

    $attachment = $this->attachmentRepository->findById($attachmentId);

    if (null === $attachment || (string) $attachment->inspectionId() !== (string) $inspectionId) {
      throw InspectionAttachmentNotFoundException::withId($command->attachmentId);
    }

    $this->attachmentRepository->delete($attachmentId);
    $this->fileStorage->delete($attachment->storagePath());

    return new DeleteInspectionAttachmentResult(
      attachmentId: (string) $attachmentId,
      inspectionId: (string) $inspectionId,
    );
  }
  // #endregion
}
