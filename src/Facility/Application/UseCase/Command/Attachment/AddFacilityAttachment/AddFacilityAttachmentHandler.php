<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\Attachment\AddFacilityAttachment;

use Facility\Application\Port\Outbound\{FacilityAttachmentRepositoryPort, FacilityRepositoryPort};
use Facility\Domain\Exception\FacilityNotFoundException;
use Facility\Domain\Model\Attachment\FacilityAttachment;
use Facility\Domain\ValueObject\{AttachmentKind, FacilityAttachmentId, FacilityId, FacilityOrganizationId, ImageDimensions};
use InvalidArgumentException;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\FileStoragePort;
use Shared\Domain\Attachment\{AttachmentConstraints, StoragePathScheme};
use Shared\Domain\Exception\InvalidValueException;
use Throwable;
use ValueError;

/**
 * UseCase AddFacilityAttachmentHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AddFacilityAttachmentHandler implements CommandHandler
{
  // #region Constructor
  public function __construct(
    private FacilityRepositoryPort $facilityRepository,
    private FacilityAttachmentRepositoryPort $attachmentRepository,
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
  public function __invoke(AddFacilityAttachmentCommand $command): AddFacilityAttachmentResult
  {
    try {
      $facilityId = FacilityId::fromString($command->facilityId);
      $organizationId = FacilityOrganizationId::fromString($command->organizationId);
      $kind = AttachmentKind::from($command->kind);
    } catch (InvalidValueException|ValueError $exception) {
      throw new InvalidArgumentException($exception->getMessage(), 0, $exception);
    }

    $facility = $this->facilityRepository->findById($facilityId);

    if (null === $facility || (string) $facility->organizationId() !== (string) $organizationId) {
      throw FacilityNotFoundException::withId($command->facilityId);
    }

    try {
      /** @var FacilityAttachmentId $attachmentId */
      $attachmentId = null === $command->attachmentId
        ? $this->uuidFactory->create(FacilityAttachmentId::class)
        : FacilityAttachmentId::fromString($command->attachmentId);
    } catch (InvalidValueException $exception) {
      throw new InvalidArgumentException($exception->getMessage(), 0, $exception);
    }

    // A client-supplied id that already exists is a retry overwriting its own
    // row, not a new attachment — it must not be rejected at the cap.
    if (null === $this->attachmentRepository->findById($attachmentId)) {
      AttachmentConstraints::validateCount($this->attachmentRepository->countByFacilityId($facilityId));
    }

    $storagePath = StoragePathScheme::build(
      module: 'facility',
      parentId: $command->facilityId,
      attachmentId: (string) $attachmentId,
      fileName: $command->fileName,
    );

    // The dimensions are metadata extracted from bytes already held in
    // memory (no I/O) — see ImageDimensions's own docblock — and only ever
    // computed for a floor plan; a document attachment carries none.
    $dimensions = AttachmentKind::FLOOR_PLAN === $kind
      ? ImageDimensions::fromContents($command->contents, $command->mimeType)
      : null;

    $attachment = FacilityAttachment::create(
      id: $attachmentId,
      facilityId: $facilityId,
      fileName: $command->fileName,
      storagePath: $storagePath,
      mimeType: $command->mimeType,
      size: $command->size,
      label: $command->label,
      kind: $kind,
      imageWidth: $dimensions?->width(),
      imageHeight: $dimensions?->height(),
    );

    $this->fileStorage->write($storagePath, $command->contents);

    try {
      $this->attachmentRepository->save($attachment);
    } catch (Throwable $dbException) {
      $this->fileStorage->delete($storagePath);

      throw $dbException;
    }

    return new AddFacilityAttachmentResult(
      attachmentId: (string) $attachment->id(),
      facilityId: (string) $attachment->facilityId(),
      fileName: $attachment->fileName(),
      mimeType: $attachment->mimeType(),
      size: $attachment->size(),
      label: $attachment->label(),
      uploadedAt: $attachment->uploadedAt(),
      kind: $attachment->kind()->value,
      isPrimaryPlan: $attachment->isPrimaryPlan(),
      imageWidth: $attachment->imageWidth(),
      imageHeight: $attachment->imageHeight(),
    );
  }
  // #endregion
}
