<?php

declare(strict_types=1);

namespace User\Application\UseCase\Query\ListUsers;

use Shared\Application\Message\QueryMessage;

/**
 * Query ListUsersQuery
 * @final
 *
 * Query to list users.
 *
 * @category Query
 * @package User\Application\UseCase\Query\ListUsers
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListUsersQuery implements QueryMessage
{
  //#region Constructor
  /**
   * Constructor
   * 
   * Initializes a new instance of the 
   * ListUsersQuery class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param int $page The page number.
   * @param int $limit The limit per page.
   */
  public function __construct(
    public readonly int $page = 1,
    public readonly int $limit = 10
  ) {}
  //#endregion
}
