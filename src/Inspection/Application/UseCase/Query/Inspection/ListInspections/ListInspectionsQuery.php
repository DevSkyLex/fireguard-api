<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\Inspection\ListInspections;

use Shared\Application\Contract\Pagination\Pagination;
use Shared\Application\Contract\Sorting\{SortDirection, Sorting};
use Shared\Application\Message\QueryMessage;

/**
 * UseCase ListInspectionsQuery.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListInspectionsQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Carries the organization-scoped filters, pagination, and ordering for inspection listing.
   *
   * @access public
   *
   * @param string $organizationId organization whose inspections are listed
   * @param ?string $equipmentId optional inspected equipment filter
   * @param ?string $facilityId optional facility filter
   * @param ?string $result optional inspection outcome filter
   * @param ?string $status optional lifecycle status filter
   * @param ?string $performedAtFrom inclusive lower bound for performed time
   * @param ?string $performedAtTo inclusive upper bound for performed time
   * @param ?string $inspectorUserId optional inspector user filter
   * @param ?string $checklistId optional checklist filter
   * @param Pagination $pagination requested page and page size
   * @param ?string $search optional free-text filter
   * @param Sorting $sorting requested field and direction for ordering
   *
   * @return void
   */
  public function __construct(
    public string $organizationId,
    public ?string $equipmentId = null,
    public ?string $facilityId = null,
    public ?string $result = null,
    public ?string $status = null,
    public ?string $performedAtFrom = null,
    public ?string $performedAtTo = null,
    public ?string $inspectorUserId = null,
    public ?string $checklistId = null,
    public Pagination $pagination = new Pagination(),
    public ?string $search = null,
    public Sorting $sorting = new Sorting('createdAt', SortDirection::ASC),
  ) {
  }
  // #endregion
}
