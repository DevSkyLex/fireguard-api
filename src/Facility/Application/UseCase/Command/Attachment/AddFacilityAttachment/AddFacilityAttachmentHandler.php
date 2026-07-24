<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\Attachment\AddFacilityAttachment;

use Facility\Application\Port\Outbound\{FacilityAttachmentRepositoryPort, FacilityRepositoryPort};
use Facility\Domain\Exception\FacilityNotFoundException;
use Facility\Domain\Model\Attachment\FacilityAttachment;
use Facility\Domain\ValueObject\{FacilityAttachmentId, FacilityId, FacilityOrganizationId};
use InvalidArgumentException;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\FileStoragePort;
use Shared\Domain\Attachment\StoragePathScheme;
use Shared\Domain\Exception\InvalidValueException;
use Throwable;

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
    } catch (InvalidValueException $exception) {
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

    $storagePath = StoragePathScheme::build(
      module: 'facility',
      parentId: $command->facilityId,
      attachmentId: (string) $attachmentId,
      fileName: $command->fileName,
    );

    $attachment = FacilityAttachment::create(
      id: $attachmentId,
      facilityId: $facilityId,
      fileName: $command->fileName,
      storagePath: $storagePath,
      mimeType: $command->mimeType,
      size: $command->size,
      label: $command->label,
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
    );
  }
  // #endregion
}
