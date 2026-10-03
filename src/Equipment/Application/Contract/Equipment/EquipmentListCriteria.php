<?php

declare(strict_types=1);

namespace Equipment\Application\Contract\Equipment;

/**
 * Filters shared by the equipment list and its matching count.
 */
final readonly class EquipmentListCriteria
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Carries optional filters shared by equipment listing and its matching count.
   *
   * @access public
   *
   * @param ?string $facilityId optional facility filter
   * @param ?string $type optional equipment type filter
   * @param ?string $status optional lifecycle status filter
   * @param ?string $brand optional brand filter
   * @param ?string $model optional model filter
   * @param ?string $subType optional equipment subtype filter
   * @param ?string $search optional text search applied to equipment fields
   * @param ?list<string> $facilityIds optional resolved subtree filter; an empty list matches nothing
   *
   * @return void
   */
  public function __construct(
    public ?string $facilityId = null,
    public ?string $type = null,
    public ?string $status = null,
    public ?string $brand = null,
    public ?string $model = null,
    public ?string $subType = null,
    public ?string $search = null,
    public ?array $facilityIds = null,
  ) {
  }
  // #endregion
}
