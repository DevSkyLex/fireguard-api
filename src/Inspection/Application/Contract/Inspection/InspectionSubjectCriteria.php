<?php

declare(strict_types=1);

namespace Inspection\Application\Contract\Inspection;

/**
 * The inspected equipment, facility and checklist filters.
 */
final readonly class InspectionSubjectCriteria
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Groups the equipment, facility, and checklist filters for inspection reads.
   *
   * @access public
   *
   * @param ?string $equipmentId optional inspected equipment identifier
   * @param ?string $facilityId optional inspected facility identifier
   * @param ?string $checklistId optional checklist identifier
   *
   * @return void
   */
  public function __construct(
    public ?string $equipmentId = null,
    public ?string $facilityId = null,
    public ?string $checklistId = null,
  ) {
  }
  // #endregion
}
