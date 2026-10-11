<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Equipment\Application\Contract\Media\EquipmentMediaView;
use Equipment\Application\Port\Outbound\EquipmentMediaReadPort;
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentAttachmentRecord;

/**
 * Projects the attachment and its owner for media use cases.
 *
 * @category Repository
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class EquipmentMediaReadRepository implements EquipmentMediaReadPort
{
  // #region Constructor
  /**
   * Constructor. @since 1.0.0
   */
  public function __construct(private EntityManagerInterface $entityManager)
  {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritdoc} @since 1.0.0
   */
  public function find(string $id, bool $forUpdate = false): ?EquipmentMediaView
  {
    if ($forUpdate) {
      $this->entityManager->getConnection()->executeStatement('SELECT id FROM equipment_attachments WHERE id = :id FOR UPDATE', ['id' => $id]);
    }
    $record = $this->entityManager->find(EquipmentAttachmentRecord::class, $id);
    if ($forUpdate && $record instanceof EquipmentAttachmentRecord) {
      $this->entityManager->refresh($record);
    }
    if (!$record instanceof EquipmentAttachmentRecord || null === $record->equipment || '' === $record->equipment->organizationId) {
      return null;
    }
    $equipment = $record->equipment;

    return new EquipmentMediaView(
      $record->id,
      $equipment->id,
      $equipment->organizationId,
      $record->fileName,
      $record->mimeType,
      $record->size,
      $record->label,
      $record->revision,
      $record->uploadedAt,
      $equipment->recordStatus,
      $equipment->interventionId,
    );
  }
  // #endregion
}
