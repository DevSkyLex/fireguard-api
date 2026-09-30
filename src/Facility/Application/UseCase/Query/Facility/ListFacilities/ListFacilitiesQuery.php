<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Query\Facility\ListFacilities;

use Shared\Application\Contract\Pagination\Pagination;
use Shared\Application\Contract\Sorting\{SortDirection, Sorting};
use Shared\Application\Message\QueryMessage;

/**
 * UseCase ListFacilitiesQuery.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListFacilitiesQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Carries the organization scope, visibility options, filters, pagination, and ordering for a facility list.
   *
   * @access public
   *
   * @param string $organizationId organization whose facilities are listed
   * @param bool $includeArchived whether archived facilities are included
   * @param Pagination $pagination requested page and page size
   * @param ?string $type optional facility type filter
   * @param ?string $status optional facility status filter
   * @param ?string $parentFacilityId optional parent facility filter
   * @param bool $rootsOnly whether only top-level facilities are returned
   * @param ?string $code optional facility code filter
   * @param ?bool $hasCoordinates whether latitude and longitude must both be set or unset, when specified
   * @param ?string $search optional text search
   * @param Sorting $sorting requested field and direction for ordering results
   *
   * @return void
   */
  public function __construct(
    public string $organizationId,
    public bool $includeArchived = false,
    public Pagination $pagination = new Pagination(),
    public ?string $type = null,
    public ?string $status = null,
    public ?string $parentFacilityId = null,
    public bool $rootsOnly = false,
    public ?string $code = null,
    public ?bool $hasCoordinates = null,
    public ?string $search = null,
    public Sorting $sorting = new Sorting('name', SortDirection::ASC),
  ) {
  }
  // #endregion
}
