<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Attachment\AddInspectionAttachment;

use Inspection\Application\Port\Outbound\Internal\{InspectionAttachmentRepositoryPort, InspectionRepositoryPort, NonConformityRepositoryPort};
use Inspection\Application\Service\AttachmentUploadAttempt;
use Inspection\Domain\Exception\{InspectionAccessDeniedException, InspectionNotFoundException, InspectionResponseConflictException, NonConformityNotFoundException};
use Inspection\Domain\Model\Attachment\InspectionAttachment;
use Inspection\Domain\ValueObject\{InspectionAttachmentFile, InspectionAttachmentId, InspectionId, InspectionOrganizationId, NonConformityId};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Contract\Transaction\CommitOutcomeUnknownException;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{FileStoragePort, LoggerPort, RootTransactionManagerPort};
use Shared\Application\Port\Outbound\UuidGeneratorPort;
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
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AddInspectionAttachmentHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Receives inspection, non-conformity, and attachment repositories plus file storage and an identifier factory.
   *
   * @access public
   *
   * @param InspectionRepositoryPort $inspectionRepository port used to load the inspection and confirm its organization scope
   * @param NonConformityRepositoryPort $nonConformityRepository port used to validate the optional non-conformity association
   * @param InspectionAttachmentRepositoryPort $attachmentRepository port used to check identity and persist attachment metadata
   * @param FileStoragePort $fileStorage port used to write file bytes and clean them up on persistence failure
   * @param UuidGeneratorPort $uuidFactory factory used when no attachment identifier was supplied
   *
   * @return void
   */
  public function __construct(
    private InspectionRepositoryPort $inspectionRepository,
    private NonConformityRepositoryPort $nonConformityRepository,
    private InspectionAttachmentRepositoryPort $attachmentRepository,
    private FileStoragePort $fileStorage,
    private UuidGeneratorPort $uuidFactory,
    private RootTransactionManagerPort $transactions,
    private OrganizationAuthorizationPort $authorization,
    private LoggerPort $logger,
    private ?\Inspection\Application\Port\Outbound\InspectionMediaLookupPort $media = null,
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
    $this->transactions->assertCanStart();
    /** @var InspectionAttachmentId $attachmentId */
    $attachmentId = null === $command->attachmentId
      ? new InspectionAttachmentId($this->uuidFactory->generate())
      : InspectionAttachmentId::fromString($command->attachmentId);
    $storagePath = StoragePathScheme::build(
      module: 'inspection',
      parentId: $command->inspectionId,
      attachmentId: (string) $attachmentId . '_' . $this->uuidFactory->generate(),
      fileName: $command->fileName,
    );
    $attempt = new AttachmentUploadAttempt();

    try {
      return $this->transactions->transactional(fn (): AddInspectionAttachmentResult => $this->persistUpload($command, $attachmentId, $storagePath, $attempt));
    } catch (Throwable $exception) {
      if ($attempt->written && !$exception instanceof CommitOutcomeUnknownException) {
        $this->compensateUpload($storagePath);
      }

      throw $exception;
    }
  }

  /**
   * Method persistUpload
   *
   * Validates the locked identity before writing attempt-owned bytes and metadata.
   *
   * @access private
   *
   * @param AddInspectionAttachmentCommand $command the requested upload
   * @param InspectionAttachmentId $attachmentId the stable attachment identity
   * @param string $storagePath the unique storage key for this attempt
   * @param AttachmentUploadAttempt $attempt the compensation state
   *
   * @return AddInspectionAttachmentResult the winning attachment snapshot
   */
  private function persistUpload(AddInspectionAttachmentCommand $command, InspectionAttachmentId $attachmentId, string $storagePath, AttachmentUploadAttempt $attempt): AddInspectionAttachmentResult
  {
    $inspectionId = InspectionId::fromString($command->inspectionId);
    $organizationId = InspectionOrganizationId::fromString($command->organizationId);
    $nonConformityId = null === $command->nonConformityId ? null : NonConformityId::fromString($command->nonConformityId);
    $this->attachmentRepository->lockUpload($command->inspectionId, (string) $attachmentId);
    $inspection = $this->inspectionRepository->findById($inspectionId);
    if (null === $inspection || (string) $inspection->organizationId() !== (string) $organizationId) {
      throw InspectionNotFoundException::withId($command->inspectionId);
    }
    $this->assertUploadAccess($command);
    $this->assertNonConformityScope($nonConformityId, $inspectionId);
    $existing = $this->attachmentRepository->findById($attachmentId);
    if (null !== $existing) {
      if ((string) $existing->inspectionId() !== $command->inspectionId || $existing->nonConformityId()?->__toString() !== $command->nonConformityId) {
        throw new InspectionResponseConflictException('Attachment client UUID is already assigned to another inspection scope.');
      }

      return $this->result($existing);
    }
    AttachmentConstraints::validateCount(null === $nonConformityId
      ? $this->attachmentRepository->countByInspectionId($inspectionId)
      : $this->attachmentRepository->countByNonConformityId($nonConformityId));
    $attachment = InspectionAttachment::create(
      id: $attachmentId,
      inspectionId: $inspectionId,
      file: new InspectionAttachmentFile(fileName: $command->fileName, storagePath: $storagePath, mimeType: $command->mimeType, size: $command->size, label: $command->label),
      nonConformityId: $nonConformityId,
    );
    $attempt->written = true;
    $this->fileStorage->write($storagePath, $command->contents);
    $this->attachmentRepository->save($attachment);

    return $this->result($attachment);
  }

  /**
   * Method assertUploadAccess
   *
   * Rechecks the actor under the upload lock before replay or mutation.
   *
   * @access private
   *
   * @param AddInspectionAttachmentCommand $command the actor and organization scope
   *
   * @return void
   */
  private function assertUploadAccess(AddInspectionAttachmentCommand $command): void
  {
    if (null === $command->userId) {
      return;
    }
    $decision = $this->authorization->resolveAccess($command->userId, $command->organizationId, 'organization.inspection.write');
    if ($decision->isOutsideScope()) {
      throw new InspectionNotFoundException('Inspection not found.');
    }
    if (!$decision->isGranted()) {
      throw new InspectionAccessDeniedException('Missing organization.inspection.write permission.');
    }
  }

  /**
   * Method assertNonConformityScope
   *
   * Verifies an optional field-proof parent belongs to the locked inspection.
   *
   * @access private
   *
   * @param ?NonConformityId $nonConformityId the optional field-proof parent
   * @param InspectionId $inspectionId the owning inspection
   *
   * @return void
   */
  private function assertNonConformityScope(?NonConformityId $nonConformityId, InspectionId $inspectionId): void
  {
    if (null === $nonConformityId) {
      return;
    }
    $nonConformity = $this->nonConformityRepository->findById($nonConformityId);
    if (null === $nonConformity || (string) $nonConformity->inspectionId() !== (string) $inspectionId) {
      throw NonConformityNotFoundException::withId((string) $nonConformityId);
    }
  }

  /**
   * Method compensateUpload
   *
   * Removes only this failed attempt while preserving the original transaction exception.
   *
   * @access private
   *
   * @param string $storagePath the attempt-owned storage key
   *
   * @return void
   */
  private function compensateUpload(string $storagePath): void
  {
    try {
      $this->fileStorage->delete($storagePath);
    } catch (Throwable $cleanupFailure) {
      try {
        $this->logger->warning('Unable to compensate a rolled-back inspection upload.', ['storagePath' => $storagePath, 'error' => $cleanupFailure->getMessage()]);
      } catch (Throwable) {
        // Diagnostics must preserve the original persistence failure.
      }
    }
  }

  private function result(InspectionAttachment $attachment): AddInspectionAttachmentResult
  {
    return new AddInspectionAttachmentResult(
      attachmentId: (string) $attachment->id(),
      inspectionId: (string) $attachment->inspectionId(),
      fileName: $attachment->fileName(),
      mimeType: $attachment->mimeType(),
      size: $attachment->size(),
      nonConformityId: $attachment->nonConformityId()?->__toString(),
      label: $attachment->label(),
      uploadedAt: $attachment->uploadedAt(),
      revision: $this->media?->attachment((string) $attachment->id())->revision ?? 1,
    );
  }
  // #endregion
}
