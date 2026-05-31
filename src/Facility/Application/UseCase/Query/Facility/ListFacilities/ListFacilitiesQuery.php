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
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListFacilitiesQuery implements QueryMessage
{
  // #region Constructor
  public function __construct(
    public string $organizationId,
    public bool $includeArchived = false,
    public Pagination $pagination = new Pagination(),
    public ?string $type = null,
    public ?string $status = null,
    public ?string $parentFacilityId = null,
    public bool $rootsOnly = false,
    public ?string $code = null,
    public ?string $search = null,
    public Sorting $sorting = new Sorting('name', SortDirection::ASC),
  ) {
  }
  // #endregion
}
