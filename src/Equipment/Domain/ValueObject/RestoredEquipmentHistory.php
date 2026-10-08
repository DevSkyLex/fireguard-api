<?php

declare(strict_types=1);

namespace Equipment\Domain\ValueObject;

use DateTimeImmutable;

/**
 * Class RestoredEquipmentHistory
 *
 * Preserves persisted equipment timestamps and replacement lineage without replaying lifecycle transitions.
 *
 * @category ValueObject
 */
final readonly class RestoredEquipmentHistory
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Carries historical provenance exactly as recorded, including optional predecessor and successor links.
   *
   * @access public
   *
   * @param DateTimeImmutable $createdAt original creation timestamp
   * @param DateTimeImmutable $updatedAt last persisted modification timestamp
   * @param ?string $predecessorEquipmentId asset replaced by this equipment, when recorded
   * @param ?string $successorEquipmentId replacement asset for this equipment, when recorded
   *
   * @return void
   */
  public function __construct(
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $updatedAt,
    public ?string $predecessorEquipmentId = null,
    public ?string $successorEquipmentId = null,
  ) {
  }
  // #endregion
}
