<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Query\Equipment\ListEquipmentAttachments;

use Equipment\Application\Port\Outbound\{AttachmentRepositoryPort, EquipmentRepositoryPort};
use Equipment\Domain\Exception\EquipmentNotFoundException;
use Equipment\Domain\ValueObject\{EquipmentId, EquipmentOrganizationId};
use Shared\Application\Message\QueryHandler;

/**
 * UseCase ListEquipmentAttachmentsHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListEquipmentAttachmentsHandler implements QueryHandler
{
  // #region Constructor
  public function __construct(
    private EquipmentRepositoryPort $equipmentRepository,
    private AttachmentRepositoryPort $attachmentRepository,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   */
  public function __invoke(ListEquipmentAttachmentsQuery $query): ListEquipmentAttachmentsResult
  {
    $equipmentId = EquipmentId::fromString($query->equipmentId);
    $organizationId = EquipmentOrganizationId::fromString($query->organizationId);

    $equipment = $this->equipmentRepository->findById($equipmentId);

    if (null === $equipment || (string) $equipment->organizationId() !== (string) $organizationId) {
      throw EquipmentNotFoundException::withId($query->equipmentId);
    }

    $attachments = $this->attachmentRepository->findByEquipmentId($equipmentId);

    $result = [];
    foreach ($attachments as $attachment) {
      $result[] = [
        'id' => (string) $attachment->id(),
        'fileName' => $attachment->fileName(),
        'mimeType' => $attachment->mimeType(),
        'size' => $attachment->size(),
        'label' => $attachment->label(),
        'uploadedAt' => $attachment->uploadedAt()->format('c'),
      ];
    }

    return new ListEquipmentAttachmentsResult($result);
  }
  // #endregion
}
