<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\Attachment\DeleteFacilityAttachment;

use Facility\Application\Port\Outbound\{FacilityAttachmentRepositoryPort, FacilityRepositoryPort};
use Facility\Domain\Exception\{FacilityAttachmentNotFoundException, FacilityNotFoundException};
use Facility\Domain\ValueObject\{FacilityAttachmentId, FacilityId, FacilityOrganizationId};
use InvalidArgumentException;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\FileStoragePort;
use Shared\Domain\Exception\InvalidValueException;

/**
 * UseCase DeleteFacilityAttachmentHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteFacilityAttachmentHandler implements CommandHandler
{
  // #region Constructor
  public function __construct(
    private FacilityRepositoryPort $facilityRepository,
    private FacilityAttachmentRepositoryPort $attachmentRepository,
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
  public function __invoke(DeleteFacilityAttachmentCommand $command): DeleteFacilityAttachmentResult
  {
    try {
      $facilityId = FacilityId::fromString($command->facilityId);
      $organizationId = FacilityOrganizationId::fromString($command->organizationId);
      $attachmentId = FacilityAttachmentId::fromString($command->attachmentId);
    } catch (InvalidValueException $exception) {
      throw new InvalidArgumentException($exception->getMessage(), 0, $exception);
    }

    $facility = $this->facilityRepository->findById($facilityId);

    if (null === $facility || (string) $facility->organizationId() !== (string) $organizationId) {
      throw FacilityNotFoundException::withId($command->facilityId);
    }

    $attachment = $this->attachmentRepository->findById($attachmentId);

    if (null === $attachment || (string) $attachment->facilityId() !== (string) $facilityId) {
      throw FacilityAttachmentNotFoundException::withId($command->attachmentId);
    }

    $this->attachmentRepository->delete($attachmentId);
    $this->fileStorage->delete($attachment->storagePath());

    return new DeleteFacilityAttachmentResult(
      attachmentId: (string) $attachmentId,
      facilityId: (string) $facilityId,
    );
  }
  // #endregion
}
