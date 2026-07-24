<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Attachment\DeleteInspectionAttachment;

use Inspection\Application\Port\Outbound\{InspectionAttachmentRepositoryPort, InspectionRepositoryPort};
use Inspection\Domain\Exception\{InspectionAttachmentNotFoundException, InspectionNotFoundException};
use Inspection\Domain\ValueObject\{InspectionAttachmentId, InspectionId, InspectionOrganizationId};
use InvalidArgumentException;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\FileStoragePort;
use Shared\Domain\Exception\InvalidValueException;

/**
 * UseCase DeleteInspectionAttachmentHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteInspectionAttachmentHandler implements CommandHandler
{
  // #region Constructor
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
    try {
      $inspectionId = InspectionId::fromString($command->inspectionId);
      $organizationId = InspectionOrganizationId::fromString($command->organizationId);
      $attachmentId = InspectionAttachmentId::fromString($command->attachmentId);
    } catch (InvalidValueException $exception) {
      throw new InvalidArgumentException($exception->getMessage(), 0, $exception);
    }

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
