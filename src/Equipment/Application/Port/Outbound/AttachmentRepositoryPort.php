<?php

declare(strict_types=1);

namespace Equipment\Application\Port\Outbound;

use Equipment\Domain\Model\Attachment\EquipmentAttachment;
use Equipment\Domain\ValueObject\{AttachmentId, EquipmentId};

/**
 * Port AttachmentRepositoryPort.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface AttachmentRepositoryPort
{
  // #region Methods
  /**
   * Method save.
   *
   * Persists an equipment attachment.
   *
   * @since 1.0.0
   *
   * @param EquipmentAttachment $attachment the attachment
   */
  public function save(EquipmentAttachment $attachment): void;

  /**
   * Method findById.
   *
   * Finds an attachment by identifier.
   *
   * @since 1.0.0
   *
   * @param AttachmentId $id the attachment identifier
   *
   * @return ?EquipmentAttachment the attachment when found
   */
  public function findById(AttachmentId $id): ?EquipmentAttachment;

  /**
   * Method findByEquipmentId.
   *
   * Lists all attachments for an equipment.
   *
   * @since 1.0.0
   *
   * @param EquipmentId $equipmentId the equipment identifier
   *
   * @return list<EquipmentAttachment> the attachment list
   */
  public function findByEquipmentId(EquipmentId $equipmentId): array;

  /**
   * Method delete.
   *
   * Deletes an attachment record.
   *
   * @since 1.0.0
   *
   * @param AttachmentId $id the attachment identifier
   */
  public function delete(AttachmentId $id): void;
  // #endregion
}
