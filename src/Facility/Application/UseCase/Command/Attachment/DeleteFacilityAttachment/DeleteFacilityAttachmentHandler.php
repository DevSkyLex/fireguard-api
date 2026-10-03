<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\Attachment\DeleteFacilityAttachment;

use Facility\Application\Port\Outbound\{FacilityAttachmentRepositoryPort, FacilityPlanReferenceCleanupPort, FacilityRepositoryPort};
use Facility\Domain\Exception\{FacilityAttachmentNotFoundException, FacilityNotFoundException};
use Facility\Domain\ValueObject\{FacilityAttachmentId, FacilityId, FacilityOrganizationId};
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{FileStoragePort, TransactionManagerPort};

/**
 * UseCase DeleteFacilityAttachmentHandler.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteFacilityAttachmentHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Receives facility and attachment repositories plus file storage to remove attachment metadata and bytes.
   *
   * @access public
   *
   * @param FacilityRepositoryPort $facilityRepository port used to validate the facility and its organization scope
   * @param FacilityAttachmentRepositoryPort $attachmentRepository port used to load and remove attachment metadata
   * @param FileStoragePort $fileStorage port used to delete the attachment bytes
   *
   * @return void
   */
  public function __construct(
    private FacilityRepositoryPort $facilityRepository,
    private FacilityAttachmentRepositoryPort $attachmentRepository,
    private FileStoragePort $fileStorage,
    private FacilityPlanReferenceCleanupPort $referenceCleanup,
    private TransactionManagerPort $transactionManager,
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
    $facilityId = FacilityId::fromString($command->facilityId);
    $organizationId = FacilityOrganizationId::fromString($command->organizationId);
    $attachmentId = FacilityAttachmentId::fromString($command->attachmentId);

    $facility = $this->facilityRepository->findById($facilityId);

    if (null === $facility || (string) $facility->organizationId() !== (string) $organizationId) {
      throw FacilityNotFoundException::withId($command->facilityId);
    }

    $attachment = $this->attachmentRepository->findById($attachmentId);

    if (null === $attachment || (string) $attachment->facilityId() !== (string) $facilityId) {
      throw FacilityAttachmentNotFoundException::withId($command->attachmentId);
    }

    $this->transactionManager->transactional(function () use ($command, $attachmentId): void {
      $this->referenceCleanup->clearForAttachment($command->organizationId, $command->attachmentId);
      $this->attachmentRepository->delete($attachmentId);
    });
    $this->fileStorage->delete($attachment->file()->storagePath);

    return new DeleteFacilityAttachmentResult(
      attachmentId: (string) $attachmentId,
      facilityId: (string) $facilityId,
    );
  }
  // #endregion
}
