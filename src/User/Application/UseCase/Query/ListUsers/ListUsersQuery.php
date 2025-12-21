<?php

declare(strict_types=1);

namespace User\Application\UseCase\Query\ListUsers;

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
   *
   * @param int $page  the page number
   * @param int $limit the limit per page
   */
  public function __construct(
    public readonly int $page = 1,
    public readonly int $limit = 10,
  ) {
  }
  // #endregion
}
