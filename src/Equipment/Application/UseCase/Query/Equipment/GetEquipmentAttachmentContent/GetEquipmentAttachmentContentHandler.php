<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Query\Equipment\GetEquipmentAttachmentContent;

use Equipment\Application\Port\Outbound\{AttachmentRepositoryPort, EquipmentRepositoryPort};
use Equipment\Domain\Exception\{AttachmentNotFoundException, EquipmentNotFoundException};
use Equipment\Domain\ValueObject\{AttachmentId, EquipmentId, EquipmentOrganizationId};
use InvalidArgumentException;
use Shared\Application\Message\QueryHandler;
use Shared\Application\Port\Outbound\FileStoragePort;
use Shared\Domain\Exception\InvalidValueException;

/**
 * UseCase GetEquipmentAttachmentContentHandler.
 *
 * Serves `GET /organizations/{organizationId}/equipment/{equipmentId}/attachments/{attachmentId}/download`.
 * The organization-level `organization.equipment.read` permission is
 * enforced by `DownloadEquipmentAttachmentController` — same gate
 * `ListEquipmentAttachmentsProvider` already applies for every other read of
 * this module's attachment surface — while THIS handler owns the per-record
 * scoping a resource-level permission check cannot prove: that the requested
 * equipment belongs to the given organization, and that the requested
 * attachment belongs to that equipment. Mirrors
 * `DeleteAttachmentHandler`/`ListEquipmentAttachmentsHandler` for that
 * ownership chain, and `Intervention\...\GetInterventionAttachmentContentHandler`
 * for reading the stored bytes directly through `FileStoragePort` rather than
 * probing with `exists()` first — a missing file is a data-integrity signal
 * the controller logs, not a normal not-found path.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetEquipmentAttachmentContentHandler implements QueryHandler
{
  // #region Constructor
  public function __construct(
    private EquipmentRepositoryPort $equipmentRepository,
    private AttachmentRepositoryPort $attachmentRepository,
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
  public function __invoke(GetEquipmentAttachmentContentQuery $query): GetEquipmentAttachmentContentResult
  {
    try {
      $equipmentId = EquipmentId::fromString($query->equipmentId);
      $organizationId = EquipmentOrganizationId::fromString($query->organizationId);
      $attachmentId = AttachmentId::fromString($query->attachmentId);
    } catch (InvalidValueException $exception) {
      throw new InvalidArgumentException($exception->getMessage(), 0, $exception);
    }

    $equipment = $this->equipmentRepository->findById($equipmentId);

    if (null === $equipment || (string) $equipment->organizationId() !== (string) $organizationId) {
      throw EquipmentNotFoundException::withId($query->equipmentId);
    }

    $attachment = $this->attachmentRepository->findById($attachmentId);

    if (null === $attachment || (string) $attachment->equipmentId() !== (string) $equipmentId) {
      throw AttachmentNotFoundException::withId($query->attachmentId);
    }

    return new GetEquipmentAttachmentContentResult(
      fileName: $attachment->fileName(),
      mimeType: $attachment->mimeType(),
      size: $attachment->size(),
      contents: $this->fileStorage->read($attachment->storagePath()),
    );
  }
  // #endregion
}
