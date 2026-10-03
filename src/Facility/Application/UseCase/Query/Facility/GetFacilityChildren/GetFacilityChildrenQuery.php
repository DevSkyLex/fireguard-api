<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Query\Facility\GetFacilityChildren;

use Shared\Application\Contract\Pagination\Pagination;
use Shared\Application\Contract\Sorting\{SortDirection, Sorting};
use Shared\Application\Message\QueryMessage;

/**
 * Class GetFacilityChildrenQuery
 *
 * Carries the criteria for the GetFacilityChildrenQuery read operation.
 *
 * @category UseCase
 */
final readonly class GetFacilityChildrenQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Initializes the GetFacilityChildrenQuery dependencies and state.
   *
   * @access public
   *
   * @param string $organizationId the organization identifier
   * @param string $facilityId the facility identifier
   * @param bool $includeArchived the optional include archived
   * @param Pagination $pagination the optional pagination
   * @param ?string $search the optional search
   * @param Sorting $sorting the optional sort configuration
   * @param bool $includePath whether to resolve ancestor breadcrumbs for the page
   *
   * @return void
   */
  public function __construct(
    public string $organizationId,
    public string $facilityId,
    public bool $includeArchived = false,
    public Pagination $pagination = new Pagination(),
    public ?string $search = null,
    public Sorting $sorting = new Sorting('name', SortDirection::ASC),
    public bool $includePath = false,
  ) {
  }
  // #endregion
}
