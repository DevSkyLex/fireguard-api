<?php

declare(strict_types=1);

namespace Facility\Domain\Model\Facility;

use DateTimeImmutable;
use Facility\Domain\ValueObject\FacilityStatus;

/** Persisted lifecycle and revision fields of a canonical facility. */
final readonly class CanonicalFacilityVersion
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Captures the canonical facility lifecycle status and optimistic-lock revision.
   *
   * @access public
   *
   * @param FacilityStatus $status facility lifecycle status
   * @param int $revision optimistic-lock revision of the canonical record
   * @param DateTimeImmutable $updatedAt time the record was last updated
   * @param ?int $levelIndex hierarchy level index, when materialized
   * @param ?float $elevationMeters optional physical floor elevation in meters
   * @param ?float $heightMeters optional physical floor height in meters
   *
   * @return void
   */
  public function __construct(
    public FacilityStatus $status,
    public int $revision,
    public DateTimeImmutable $updatedAt,
    public ?int $levelIndex = null,
    public ?float $elevationMeters = null,
    public ?float $heightMeters = null,
    public ?string $customerId = null,
  ) {
  }
  // #endregion
}
