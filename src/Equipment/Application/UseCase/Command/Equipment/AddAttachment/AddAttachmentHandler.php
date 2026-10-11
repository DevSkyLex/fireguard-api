<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Command\Equipment\AddAttachment;

use Equipment\Application\Port\Outbound\{EquipmentMediaLockPort, EquipmentMediaReadPort};
use Equipment\Application\Port\Outbound\Internal\{AttachmentRepositoryPort, CanonicalEquipmentRepositoryPort, EquipmentRepositoryPort};
use Equipment\Application\Service\EquipmentMediaAccess;
use Equipment\Domain\Exception\{AttachmentNotFoundException, EquipmentNotFoundException};
use Equipment\Domain\Model\Attachment\EquipmentAttachment;
use Equipment\Domain\ValueObject\{AttachmentId, EquipmentId, EquipmentOrganizationId, EquipmentRecordStatus};
use Intervention\Application\Port\Inbound\InterventionResourcesPort;
use Shared\Application\Contract\Transaction\CommitOutcomeUnknownException;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{FileCleanupPort, FileStoragePort, LoggerPort, RootTransactionManagerPort};
use Shared\Application\Port\Outbound\UuidGeneratorPort;
use Shared\Domain\Attachment\AttachmentConstraints;
use Throwable;

use function basename;
use function sprintf;

/**
 * Commits the upload, intervention revision and durable cleanup as one media mutation.
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
   * Constructor. @since 1.0.0
   */
  public function __construct(
    private EquipmentRepositoryPort $equipmentRepository,
    private AttachmentRepositoryPort $attachmentRepository,
    private FileStoragePort $fileStorage,
    private UuidGeneratorPort $uuidFactory,
    private RootTransactionManagerPort $transactions,
    private EquipmentMediaLockPort $locks,
    private CanonicalEquipmentRepositoryPort $canonical,
    private EquipmentMediaReadPort $media,
    private EquipmentMediaAccess $access,
    private InterventionResourcesPort $interventions,
    private FileCleanupPort $cleanup,
    private LoggerPort $logger,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * @since 1.0.0
   */
  public function __invoke(AddAttachmentCommand $command): AddAttachmentResult
  {
    $this->transactions->assertCanStart();
    $attemptPath = null;

    try {
      return $this->transactions->transactional(function () use ($command, &$attemptPath): AddAttachmentResult {
        $equipmentId = EquipmentId::fromString($command->equipmentId);
        $organizationId = EquipmentOrganizationId::fromString($command->organizationId);
        $this->locks->lock($command->equipmentId);
        $equipment = $this->equipmentRepository->findById($equipmentId);
        if (null === $equipment || (string) $equipment->organizationId() !== (string) $organizationId) {
          throw EquipmentNotFoundException::withId($command->equipmentId);
        }
        $scope = $this->canonical->findById($equipmentId);
        $interventionId = $command->interventionId ?? (EquipmentRecordStatus::DRAFT === $scope?->recordStatus() ? $scope->interventionId() : null);
        if (null !== $command->userId) {
          $this->access->assertWrite($command->equipmentId, $command->organizationId, $scope?->interventionId(), $interventionId, $command->userId);
        }
        /** @var AttachmentId $attachmentId */
        $attachmentId = null === $command->attachmentId ? new AttachmentId($this->uuidFactory->generate()) : AttachmentId::fromString($command->attachmentId);
        $existing = $this->attachmentRepository->findById($attachmentId);
        if (null !== $existing) {
          if ((string) $existing->equipmentId() !== (string) $equipmentId) {
            throw AttachmentNotFoundException::withId((string) $attachmentId);
          }

          return $this->result($existing);
        }
        AttachmentConstraints::validateCount($this->attachmentRepository->countByEquipmentId($equipmentId));
        $attemptPath = sprintf('equipment/%s/attachments/%s/%s_%s', $command->equipmentId, (string) $attachmentId, $this->uuidFactory->generate(), basename($command->fileName));
        $attachment = EquipmentAttachment::create($attachmentId, $equipmentId, $command->fileName, $attemptPath, $command->mimeType, $command->size, $command->label);
        $this->fileStorage->write($attemptPath, $command->contents);
        $persisted = $this->attachmentRepository->saveIfAbsent($attachment);
        if ($persisted->storagePath() !== $attemptPath) {
          $this->cleanup->request($attemptPath);
        } else {
          $this->interventions->touchDraftIntervention($interventionId);
        }

        return $this->result($persisted);
      });
    } catch (Throwable $failure) {
      /** @var string|null $attemptPath the callback retains its written key even when the transaction throws */
      if (null !== $attemptPath && !$failure instanceof CommitOutcomeUnknownException) {
        try {
          $this->fileStorage->delete($attemptPath);
        } catch (Throwable $cleanupFailure) {
          try {
            $this->logger->warning('Equipment upload compensation failed.', ['storagePath' => $attemptPath, 'error' => $cleanupFailure->getMessage()]);
          } catch (Throwable) {
            // Compensation diagnostics must preserve the primary transaction failure.
          }
        }
      }

      throw $failure;
    }
  }

  /**
   * @since 1.0.0
   */
  private function result(EquipmentAttachment $attachment): AddAttachmentResult
  {
    return new AddAttachmentResult(
      (string) $attachment->id(),
      (string) $attachment->equipmentId(),
      $attachment->fileName(),
      $attachment->mimeType(),
      $attachment->size(),
      $attachment->label(),
      $attachment->uploadedAt(),
      $this->media->find((string) $attachment->id())->revision ?? 1,
    );
  }
  // #endregion
}
