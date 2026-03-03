<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Command\Equipment\DeleteAttachment;

use Equipment\Application\Port\Outbound\{AttachmentRepositoryPort, EquipmentRepositoryPort};
use Equipment\Domain\Exception\{AttachmentNotFoundException, EquipmentNotFoundException};
use Equipment\Domain\ValueObject\{AttachmentId, EquipmentId, EquipmentOrganizationId};
use InvalidArgumentException;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\FileStoragePort;
use Shared\Domain\Exception\InvalidValueException;

/**
 * UseCase DeleteAttachmentHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteAttachmentHandler implements CommandHandler
{
  // #region Constructor
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
    try {
      $equipmentId = EquipmentId::fromString($command->equipmentId);
      $organizationId = EquipmentOrganizationId::fromString($command->organizationId);
      $attachmentId = AttachmentId::fromString($command->attachmentId);
    } catch (InvalidValueException $exception) {
      throw new InvalidArgumentException($exception->getMessage(), 0, $exception);
    }

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
