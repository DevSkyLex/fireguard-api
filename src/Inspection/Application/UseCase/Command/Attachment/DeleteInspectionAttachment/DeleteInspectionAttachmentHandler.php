<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Attachment\DeleteInspectionAttachment;

use Inspection\Application\Port\Outbound\InspectionMediaLookupPort;
use Inspection\Application\Port\Outbound\Internal\{InspectionAttachmentRepositoryPort, InspectionRepositoryPort};
use Inspection\Domain\Exception\{InspectionAccessDeniedException, InspectionRevisionMismatchException};
use Inspection\Domain\Exception\{InspectionAttachmentNotFoundException, InspectionNotFoundException};
use Inspection\Domain\ValueObject\{InspectionAttachmentId, InspectionId, InspectionOrganizationId};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{FileCleanupPort, RootTransactionManagerPort};
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyExecutionPort;

/**
 * UseCase DeleteInspectionAttachmentHandler.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteInspectionAttachmentHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Receives inspection and attachment repositories plus file storage to remove the attachment metadata and bytes.
   *
   * @access public
   *
   * @param InspectionRepositoryPort $inspectionRepository port used to validate the inspection and organization scope
   * @param InspectionAttachmentRepositoryPort $attachmentRepository port used to load and remove attachment metadata
   * @param FileCleanupPort $fileCleanup durably removes bytes after commit
   *
   * @return void
   */
  public function __construct(
    private InspectionRepositoryPort $inspectionRepository,
    private InspectionAttachmentRepositoryPort $attachmentRepository,
    private FileCleanupPort $fileCleanup,
    private RootTransactionManagerPort $transactions,
    private InspectionMediaLookupPort $media,
    private OrganizationAuthorizationPort $authorization,
    private ?HttpIdempotencyExecutionPort $idempotency = null,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   */
  public function __invoke(DeleteInspectionAttachmentCommand $command): DeleteInspectionAttachmentResult
  {
    return $this->transactions->transactional(function () use ($command): DeleteInspectionAttachmentResult {
      $inspectionId = InspectionId::fromString($command->inspectionId);
      $organizationId = InspectionOrganizationId::fromString($command->organizationId);
      $attachmentId = InspectionAttachmentId::fromString($command->attachmentId);

      $inspection = $this->inspectionRepository->findById($inspectionId);

      if (null === $inspection || (string) $inspection->organizationId() !== (string) $organizationId) {
        throw InspectionNotFoundException::withId($command->inspectionId);
      }

      $attachment = $this->attachmentRepository->findById($attachmentId);

      if (null === $attachment || (string) $attachment->inspectionId() !== (string) $inspectionId) {
        throw InspectionAttachmentNotFoundException::withId($command->attachmentId);
      }

      $stored = $this->media->attachment($command->attachmentId, lock: true);
      if (null === $stored) {
        throw new InspectionAttachmentNotFoundException('Attachment not found.');
      }
      if (null !== $command->expectedRevision && $stored->revision !== $command->expectedRevision) {
        throw InspectionRevisionMismatchException::stale();
      }

      $this->assertDeleteAccess($command);
      $this->attachmentRepository->delete($attachmentId);
      $this->fileCleanup->request($attachment->storagePath());

      $result = new DeleteInspectionAttachmentResult(
        attachmentId: (string) $attachmentId,
        inspectionId: (string) $inspectionId,
      );
      $this->idempotency?->captureResult($result);

      return $result;
    });
  }

  /**
   * Method assertDeleteAccess
   *
   * Preserves the scoped denial checks after the locked attachment revision comparison.
   *
   * @access private
   *
   * @param DeleteInspectionAttachmentCommand $command the actor and organization scope
   *
   * @return void
   */
  private function assertDeleteAccess(DeleteInspectionAttachmentCommand $command): void
  {
    if (null === $command->userId) {
      return;
    }
    $decision = $this->authorization->resolveAccess($command->userId, $command->organizationId, 'organization.inspection.write');
    if ($decision->isOutsideScope()) {
      throw new InspectionNotFoundException('Non-conformity not found.');
    }
    if (!$decision->isGranted()) {
      throw new InspectionAccessDeniedException('Missing organization.inspection.write permission.');
    }
  }
  // #endregion
}
