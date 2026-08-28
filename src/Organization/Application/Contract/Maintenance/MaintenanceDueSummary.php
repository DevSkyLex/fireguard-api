<?php

declare(strict_types=1);

namespace Organization\Application\Contract\Maintenance;

use DateTimeImmutable;

/**
 * Contract MaintenanceDueSummary.
 *
 * Read model exposed outside the Maintenance module: one due (or overdue)
 * maintenance deadline of the organization's weekly digest, without
 * depending on the Maintenance module's Domain or Infrastructure layers.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MaintenanceDueSummary
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $equipmentId the tracked equipment identifier
   * @param ?string $facilityId the owning facility identifier, when set
   * @param string $equipmentType the equipment type value
   * @param DateTimeImmutable $nextDueAt the next inspection due datetime
   * @param bool $overdue whether the deadline is already past
   */
  public function __construct(
    public string $equipmentId,
    public ?string $facilityId,
    public string $equipmentType,
    public DateTimeImmutable $nextDueAt,
    public bool $overdue,
  ) {
  }
  // #endregion
}
