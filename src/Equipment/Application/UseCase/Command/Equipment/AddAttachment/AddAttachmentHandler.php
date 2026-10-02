<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Command\Equipment\AddAttachment;

use Equipment\Application\Port\Outbound\{AttachmentRepositoryPort, EquipmentRepositoryPort};
use Equipment\Domain\Exception\{AttachmentNotFoundException, EquipmentNotFoundException};
use Equipment\Domain\Model\Attachment\EquipmentAttachment;
use Equipment\Domain\ValueObject\{AttachmentId, EquipmentId, EquipmentOrganizationId};
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\FileStoragePort;
use Shared\Domain\Attachment\AttachmentConstraints;
use Throwable;

use function basename;
use function sprintf;

/**
 * UseCase AddAttachmentHandler.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AddAttachmentHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Receives equipment and attachment repositories, file storage, and an identifier factory to persist an attachment.
   *
   * @access public
   *
   * @param EquipmentRepositoryPort $equipmentRepository port used to load and validate the organization-scoped equipment
   * @param AttachmentRepositoryPort $attachmentRepository port used to check attachment identity and persist the attachment record
   * @param FileStoragePort $fileStorage port used to write the file and remove it if persistence fails
   * @param UuidFactory $uuidFactory factory used to create an attachment identifier when the caller supplied none
   *
   * @return void
   */
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
    $equipmentId = EquipmentId::fromString($command->equipmentId);
    $organizationId = EquipmentOrganizationId::fromString($command->organizationId);

    $equipment = $this->equipmentRepository->findById($equipmentId);

    if (null === $equipment || (string) $equipment->organizationId() !== (string) $organizationId) {
      throw EquipmentNotFoundException::withId($command->equipmentId);
    }

    /** @var AttachmentId $attachmentId */
    $attachmentId = null === $command->attachmentId
      ? $this->uuidFactory->create(AttachmentId::class)
      : AttachmentId::fromString($command->attachmentId);

    $existing = $this->attachmentRepository->findById($attachmentId);
    if (null !== $existing) {
      if ((string) $existing->equipmentId() !== (string) $equipmentId) {
        throw AttachmentNotFoundException::withId((string) $attachmentId);
      }

      return $this->result($existing);
    }
    AttachmentConstraints::validateCount($this->attachmentRepository->countByEquipmentId($equipmentId));

    $storagePath = sprintf(
      'equipment/%s/attachments/%s/%s_%s',
      $command->equipmentId,
      (string) $attachmentId,
      $this->uuidFactory->generateRaw(),
      basename($command->fileName),
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

    $this->fileStorage->write($storagePath, $command->contents);

    try {
      $persisted = $this->attachmentRepository->saveIfAbsent($attachment);
    } catch (Throwable $dbException) {
      $this->fileStorage->delete($storagePath);

      throw $dbException;
    }

    if ($persisted->storagePath() !== $storagePath) {
      $this->fileStorage->delete($storagePath);
    }

    return $this->result($persisted);
  }

  /**
   * Maps the stored upload, including idempotent replay, to its response.
   *
   * @param EquipmentAttachment $attachment stored attachment
   *
   * @return AddAttachmentResult upload metadata
   */
  private function result(EquipmentAttachment $attachment): AddAttachmentResult
  {
    return new AddAttachmentResult(
      attachmentId: (string) $attachment->id(),
      equipmentId: (string) $attachment->equipmentId(),
      fileName: $attachment->fileName(),
      mimeType: $attachment->mimeType(),
      size: $attachment->size(),
      label: $attachment->label(),
      uploadedAt: $attachment->uploadedAt(),
    );
  }
  // #endregion
}
