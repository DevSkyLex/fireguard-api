<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Command\Equipment\AddAttachment;

use Equipment\Application\Port\Outbound\{AttachmentRepositoryPort, EquipmentRepositoryPort};
use Equipment\Domain\Exception\EquipmentNotFoundException;
use Equipment\Domain\Model\Attachment\EquipmentAttachment;
use Equipment\Domain\ValueObject\{AttachmentId, EquipmentId, EquipmentOrganizationId};
use InvalidArgumentException;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\FileStoragePort;
use Shared\Domain\Exception\InvalidValueException;
use Throwable;

use function sprintf;

/**
 * UseCase AddAttachmentHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AddAttachmentHandler implements CommandHandler
{
  // #region Constructor
  public function __construct(
    private EquipmentRepositoryPort $equipmentRepository,
    private AttachmentRepositoryPort $attachmentRepository,
    private FileStoragePort $fileStorage,
    private UuidFactory $uuidFactory,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   */
  public function __invoke(AddAttachmentCommand $command): AddAttachmentResult
  {
    try {
      $equipmentId = EquipmentId::fromString($command->equipmentId);
      $organizationId = EquipmentOrganizationId::fromString($command->organizationId);
    } catch (InvalidValueException $exception) {
      throw new InvalidArgumentException($exception->getMessage(), 0, $exception);
    }

    $equipment = $this->equipmentRepository->findById($equipmentId);

    if (null === $equipment || (string) $equipment->organizationId() !== (string) $organizationId) {
      throw EquipmentNotFoundException::withId($command->equipmentId);
    }

    /** @var AttachmentId $attachmentId */
    $attachmentId = $this->uuidFactory->create(AttachmentId::class);

    $storagePath = sprintf(
      'equipment/%s/attachments/%s_%s',
      $command->equipmentId,
      (string) $attachmentId,
      $command->fileName,
    );

    $attachment = EquipmentAttachment::create(
      id: $attachmentId,
      equipmentId: $equipmentId,
      fileName: $command->fileName,
      storagePath: $storagePath,
      mimeType: $command->mimeType,
      size: $command->size,
      label: $command->label,
    );

    $this->attachmentRepository->save($attachment);

    try {
      $this->fileStorage->write($storagePath, $command->contents);
    } catch (Throwable $storageException) {
      $this->attachmentRepository->delete($attachment->id());

      throw $storageException;
    }

    return new AddAttachmentResult(
      attachmentId: (string) $attachment->id(),
      equipmentId: (string) $attachment->equipmentId(),
      fileName: $attachment->fileName(),
      storagePath: $attachment->storagePath(),
      mimeType: $attachment->mimeType(),
      size: $attachment->size(),
      label: $attachment->label(),
      uploadedAt: $attachment->uploadedAt(),
    );
  }
  // #endregion
}
