<?php

declare(strict_types=1);

namespace Facility\Domain\Model\Facility;

use DateTimeImmutable;
use Facility\Domain\ValueObject\FacilityStatus;

/** Persisted status and timestamps of a facility aggregate. */
final readonly class FacilityLifecycle
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Captures the facility status and aggregate creation and update timestamps.
   *
   * @access public
   *
   * @param FacilityStatus $status current facility status
   * @param DateTimeImmutable $createdAt time the facility was created
   * @param DateTimeImmutable $updatedAt time the facility was last updated
   *
   * @return void
   */
  public function __construct(
    public FacilityStatus $status,
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $updatedAt,
  ) {
  }
  // #endregion
}
