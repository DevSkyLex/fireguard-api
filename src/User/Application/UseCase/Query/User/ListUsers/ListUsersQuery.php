<?php

declare(strict_types=1);

namespace User\Application\UseCase\Query\User\ListUsers;

use Shared\Application\Contract\Pagination\Pagination;
use Shared\Application\Contract\Sorting\{SortDirection, Sorting};
use Shared\Application\Message\QueryMessage;

/**
 * Query ListUsersQuery.
 *
 * @category Query
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListUsersQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * ListUsersQuery class.
   *
   * @since 1.0.0
   */
  public function __construct(
    public readonly Pagination $pagination = new Pagination(),
    public readonly ?string $search = null,
    public readonly Sorting $sorting = new Sorting('createdAt', SortDirection::ASC),
    public readonly ?string $tenantId = null,
  ) {
  }
  // #endregion
}
