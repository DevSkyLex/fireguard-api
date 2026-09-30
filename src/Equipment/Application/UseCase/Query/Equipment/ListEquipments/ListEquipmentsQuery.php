<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Query\Equipment\ListEquipments;

use Shared\Application\Contract\Pagination\Pagination;
use Shared\Application\Contract\Sorting\{SortDirection, Sorting};
use Shared\Application\Message\QueryMessage;

/**
 * UseCase ListEquipmentsQuery.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListEquipmentsQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Carries organization-scoped filters, pagination, and ordering for an equipment list request.
   *
   * @access public
   *
   * @param string $organizationId organization whose equipment is listed
   * @param ?string $facilityId optional facility filter
   * @param ?string $type optional equipment type filter
   * @param ?string $status optional lifecycle status filter
   * @param ?string $brand optional brand filter
   * @param ?string $model optional model filter
   * @param ?string $subType optional subtype filter
   * @param Pagination $pagination requested page and page size
   * @param ?string $search optional text search
   * @param Sorting $sorting requested equipment sort field and direction
   * @param ?string $maintenanceDueStatus optional maintenance due-state filter
   *
   * @return void
   */
  public function __construct(
    public string $organizationId,
    public ?string $facilityId = null,
    public ?string $type = null,
    public ?string $status = null,
    public ?string $brand = null,
    public ?string $model = null,
    public ?string $subType = null,
    public Pagination $pagination = new Pagination(),
    public ?string $search = null,
    public Sorting $sorting = new Sorting('createdAt', SortDirection::ASC),
    public ?string $maintenanceDueStatus = null,
  ) {
  }
  // #endregion
}
