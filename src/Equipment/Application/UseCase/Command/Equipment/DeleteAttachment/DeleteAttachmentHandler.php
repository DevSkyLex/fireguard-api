<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Command\Equipment\DeleteAttachment;

use Equipment\Application\Port\Outbound\{AttachmentRepositoryPort, EquipmentRepositoryPort};
use Equipment\Domain\Exception\{AttachmentNotFoundException, EquipmentNotFoundException};
use Equipment\Domain\ValueObject\{AttachmentId, EquipmentId, EquipmentOrganizationId};
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\FileStoragePort;

/**
 * UseCase DeleteAttachmentHandler.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteAttachmentHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Receives equipment and attachment repositories plus file storage to remove both attachment metadata and its stored file.
   *
   * @access public
   *
   * @param EquipmentRepositoryPort $equipmentRepository port used to verify the equipment and its organization
   * @param AttachmentRepositoryPort $attachmentRepository port used to load and remove attachment metadata
   * @param FileStoragePort $fileStorage port used to delete the attachment bytes
   *
   * @return void
   */
  public function __construct(
    private EquipmentRepositoryPort $equipmentRepository,
    private AttachmentRepositoryPort $attachmentRepository,
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
  public function __invoke(DeleteAttachmentCommand $command): DeleteAttachmentResult
  {
    $equipmentId = EquipmentId::fromString($command->equipmentId);
    $organizationId = EquipmentOrganizationId::fromString($command->organizationId);
    $attachmentId = AttachmentId::fromString($command->attachmentId);

    $equipment = $this->equipmentRepository->findById($equipmentId);

    if (null === $equipment || (string) $equipment->organizationId() !== (string) $organizationId) {
      throw EquipmentNotFoundException::withId($command->equipmentId);
    }

    $attachment = $this->attachmentRepository->findById($attachmentId);

    if (null === $attachment || (string) $attachment->equipmentId() !== (string) $equipmentId) {
      throw AttachmentNotFoundException::withId($command->attachmentId);
    }

    $this->attachmentRepository->delete($attachmentId);
    $this->fileStorage->delete($attachment->storagePath());

    return new DeleteAttachmentResult(
      attachmentId: (string) $attachmentId,
      equipmentId: (string) $equipmentId,
    );
  }
  // #endregion
}
