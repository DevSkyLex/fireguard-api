<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\Checklist\ListChecklists;

use Shared\Application\Contract\Pagination\Pagination;
use Shared\Application\Contract\Sorting\{SortDirection, Sorting};
use Shared\Application\Message\QueryMessage;

/**
 * UseCase ListChecklistsQuery.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListChecklistsQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Carries organization-scoped status, search, pagination, and ordering for checklist listing.
   *
   * @access public
   *
   * @param string $organizationId organization whose checklists are listed
   * @param ?string $status optional lifecycle status filter
   * @param Pagination $pagination requested page and page size
   * @param ?string $search optional text search
   * @param Sorting $sorting requested sort field and direction
   *
   * @return void
   */
  public function __construct(
    public string $organizationId,
    public ?string $status = null,
    public Pagination $pagination = new Pagination(),
    public ?string $search = null,
    public Sorting $sorting = new Sorting('createdAt', SortDirection::DESC),
  ) {
  }
  // #endregion
}
