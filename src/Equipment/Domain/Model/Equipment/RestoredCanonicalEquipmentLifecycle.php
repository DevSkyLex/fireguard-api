<?php

declare(strict_types=1);

namespace Equipment\Domain\Model\Equipment;

use DateTimeImmutable;
use Equipment\Domain\ValueObject\{EquipmentRecordStatus, EquipmentStatus};

/** Persisted publication and optimistic-lock state of a canonical equipment row. */
final readonly class RestoredCanonicalEquipmentLifecycle
{
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
