<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Attachment\AddInspectionAttachment;

use Inspection\Application\Port\Outbound\{InspectionAttachmentRepositoryPort, InspectionRepositoryPort, NonConformityRepositoryPort};
use Inspection\Domain\Exception\{InspectionNotFoundException, NonConformityNotFoundException};
use Inspection\Domain\Model\Attachment\InspectionAttachment;
use Inspection\Domain\ValueObject\{InspectionAttachmentId, InspectionId, InspectionOrganizationId, NonConformityId};
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\FileStoragePort;
use Shared\Domain\Attachment\{AttachmentConstraints, StoragePathScheme};
use Throwable;

/**
 * UseCase AddInspectionAttachmentHandler.
 *
 * Adds either an inspection-level document or, when `nonConformityId` is
 * set, a non-conformity field-proof photo. Both persist to the same
 * `inspection_attachments` table (see `src/Inspection/MODULE.md`).
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AddInspectionAttachmentHandler implements CommandHandler
{
  // #region Constructor
  public function __construct(
    private InspectionRepositoryPort $inspectionRepository,
    private NonConformityRepositoryPort $nonConformityRepository,
    private InspectionAttachmentRepositoryPort $attachmentRepository,
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
  public function __invoke(AddInspectionAttachmentCommand $command): AddInspectionAttachmentResult
  {
    $inspectionId = InspectionId::fromString($command->inspectionId);
    $organizationId = InspectionOrganizationId::fromString($command->organizationId);
    $nonConformityId = null === $command->nonConformityId ? null : NonConformityId::fromString($command->nonConformityId);

    $inspection = $this->inspectionRepository->findById($inspectionId);

    if (null === $inspection || (string) $inspection->organizationId() !== (string) $organizationId) {
      throw InspectionNotFoundException::withId($command->inspectionId);
    }

    if (null !== $nonConformityId) {
      $nonConformity = $this->nonConformityRepository->findById($nonConformityId);
      if (null === $nonConformity || (string) $nonConformity->inspectionId() !== (string) $inspectionId) {
        throw NonConformityNotFoundException::withId((string) $nonConformityId);
      }
    }

    /** @var InspectionAttachmentId $attachmentId */
    $attachmentId = null === $command->attachmentId
      ? $this->uuidFactory->create(InspectionAttachmentId::class)
      : InspectionAttachmentId::fromString($command->attachmentId);

    // A client-supplied id that already exists is a retry overwriting its own
    // row, not a new attachment — it must not be rejected at the cap. Each
    // bucket is capped against the list it feeds: inspection-level documents
    // and a non-conformity's field-proof photos are counted separately.
    if (null === $this->attachmentRepository->findById($attachmentId)) {
      AttachmentConstraints::validateCount(
        null === $nonConformityId
          ? $this->attachmentRepository->countByInspectionId($inspectionId)
          : $this->attachmentRepository->countByNonConformityId($nonConformityId),
      );
    }

    $storagePath = StoragePathScheme::build(
      module: 'inspection',
      parentId: $command->inspectionId,
      attachmentId: (string) $attachmentId,
      fileName: $command->fileName,
    );

    $attachment = InspectionAttachment::create(
      id: $attachmentId,
      inspectionId: $inspectionId,
      fileName: $command->fileName,
      storagePath: $storagePath,
      mimeType: $command->mimeType,
      size: $command->size,
      nonConformityId: $nonConformityId,
      label: $command->label,
    );

    $this->fileStorage->write($storagePath, $command->contents);

    try {
      $this->attachmentRepository->save($attachment);
    } catch (Throwable $dbException) {
      $this->fileStorage->delete($storagePath);

      throw $dbException;
    }

    return new AddInspectionAttachmentResult(
      attachmentId: (string) $attachment->id(),
      inspectionId: (string) $attachment->inspectionId(),
      fileName: $attachment->fileName(),
      mimeType: $attachment->mimeType(),
      size: $attachment->size(),
      nonConformityId: null === $attachment->nonConformityId() ? null : (string) $attachment->nonConformityId(),
      label: $attachment->label(),
      uploadedAt: $attachment->uploadedAt(),
    );
  }
  // #endregion
}
