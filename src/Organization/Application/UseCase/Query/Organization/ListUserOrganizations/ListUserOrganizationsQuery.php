<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\ListUserOrganizations;

use Shared\Application\Contract\Pagination\Pagination;
use Shared\Application\Contract\Sorting\{SortDirection, Sorting};
use Shared\Application\Message\QueryMessage;

/**
 * UseCase ListUserOrganizationsQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListUserOrganizationsQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * ListUserOrganizationsQuery class.
   *
   * @since 1.0.0
   *
   * @param string $userId the user ID
   * @param Pagination $pagination the pagination settings
   * @param string|null $status the organization status filter
   * @param string|null $search the search term
   * @param Sorting $sorting the sorting settings
   */
  public function __construct(
    public string $userId,
    public Pagination $pagination = new Pagination(),
    public ?string $status = null,
    public ?string $search = null,
    public Sorting $sorting = new Sorting('name', SortDirection::ASC),
  ) {
  }
  // #endregion
}
