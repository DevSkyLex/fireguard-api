<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Command\Equipment\DeleteAttachment;

use Equipment\Application\Port\Outbound\{EquipmentMediaLockPort, EquipmentMediaReadPort};
use Equipment\Application\Port\Outbound\Internal\{AttachmentRepositoryPort, EquipmentRepositoryPort};
use Equipment\Application\Service\EquipmentMediaAccess;
use Equipment\Domain\Exception\{AttachmentNotFoundException, EquipmentNotFoundException, EquipmentRevisionMismatchException};
use Equipment\Domain\ValueObject\{AttachmentId, EquipmentId, EquipmentOrganizationId};
use Intervention\Application\Port\Inbound\InterventionResourcesPort;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{FileCleanupPort, RootTransactionManagerPort};

/**
 * Commits attachment removal and durable byte cleanup before delivery.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteAttachmentHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor. @since 1.0.0
   */
  public function __construct(
    private EquipmentRepositoryPort $equipmentRepository,
    private AttachmentRepositoryPort $attachmentRepository,
    private RootTransactionManagerPort $transactions,
    private EquipmentMediaLockPort $locks,
    private EquipmentMediaReadPort $media,
    private EquipmentMediaAccess $access,
    private InterventionResourcesPort $interventions,
    private FileCleanupPort $cleanup,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * @since 1.0.0
   */
  public function __invoke(DeleteAttachmentCommand $command): DeleteAttachmentResult
  {
    return $this->transactions->transactional(function () use ($command): DeleteAttachmentResult {
      $equipmentId = EquipmentId::fromString($command->equipmentId);
      $organizationId = EquipmentOrganizationId::fromString($command->organizationId);
      $attachmentId = AttachmentId::fromString($command->attachmentId);
      $this->locks->lock($command->equipmentId);
      $equipment = $this->equipmentRepository->findById($equipmentId);
      if (null === $equipment || (string) $equipment->organizationId() !== (string) $organizationId) {
        throw EquipmentNotFoundException::withId($command->equipmentId);
      }
      $attachment = $this->attachmentRepository->findById($attachmentId);
      if (null === $attachment || (string) $attachment->equipmentId() !== (string) $equipmentId) {
        throw AttachmentNotFoundException::withId($command->attachmentId);
      }
      $view = $this->media->find($command->attachmentId, forUpdate: true);
      $interventionId = $command->interventionId ?? ('draft' === $view?->recordStatus ? $view->interventionId : null);
      if (null !== $command->userId) {
        $this->access->assertWrite($command->equipmentId, $command->organizationId, $view?->interventionId, $interventionId, $command->userId);
      }
      if (null !== $command->expectedRevision && $view?->revision !== $command->expectedRevision) {
        throw EquipmentRevisionMismatchException::stale();
      }
      $this->attachmentRepository->delete($attachmentId);
      $this->cleanup->request($attachment->storagePath());
      $this->interventions->touchDraftIntervention($interventionId);

      return new DeleteAttachmentResult((string) $attachmentId, (string) $equipmentId);
    });
  }
  // #endregion
}
