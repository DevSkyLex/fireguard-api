<?php

declare(strict_types=1);

namespace Equipment\Domain\Model\Equipment;

use DateTimeImmutable;
use Equipment\Domain\ValueObject\{EquipmentRecordStatus, EquipmentStatus};

/** Persisted publication and optimistic-lock state of a canonical equipment row. */
final readonly class RestoredCanonicalEquipmentLifecycle
{
  /**
   * Method __construct
   *
   * Restores the canonical equipment publication state and optimistic-lock revision.
   *
   * @access public
   *
   * @param EquipmentRecordStatus $recordStatus publication state of the canonical persistence record
   * @param ?string $interventionId intervention linked to this equipment, when present
   * @param EquipmentStatus $status operational status restored for the equipment
   * @param ?DateTimeImmutable $commissionedAt commissioning time, when the equipment has been commissioned
   * @param int $revision optimistic-lock revision persisted for the equipment
   * @param DateTimeImmutable $updatedAt time the equipment record was last updated
   *
   * @return void
   */
  public function __construct(
    public EquipmentRecordStatus $recordStatus,
    public ?string $interventionId,
    public EquipmentStatus $status,
    public ?DateTimeImmutable $commissionedAt,
    public int $revision,
    public DateTimeImmutable $updatedAt,
  ) {
  }
}
