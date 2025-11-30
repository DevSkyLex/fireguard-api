<?php

declare(strict_types=1);

namespace User\Application\UseCase\Query\ListUsers;

use Shared\Application\Handler\QueryHandler;
use Shared\Application\Message\{
  QueryMessage,
};
use Shared\Application\Query\PaginatedResult;
use User\Application\Port\Outbound\UserRepositoryPort;
use User\Domain\Model\User;

use function count;

/**
 * Handler ListUsersHandler
 * @final
 *
 * Handler for ListUsersQuery.
 *
 * @category Handler
 * @package User\Application\UseCase\Query\ListUsers
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListUsersHandler implements QueryHandler
{
  //#region Constructor
  /**
   * Constructor
   * 
   * Initializes a new instance of the 
   * ListUsersHandler class.
   * 
   * @access public
   * @since 1.0.0
   *
   * @param UserRepositoryPort $userRepository The user repository.
   */
  public function __construct(
    private readonly UserRepositoryPort $userRepository
  ) {
  }
  //#endregion

  //#region Methods
  /**
   * Method __invoke
   *
   * Handles the query.
   * 
   * @access public
   * @since 1.0.0
   *
   * @param QueryMessage $query The query.
   * 
   * @return PaginatedResult<User> The result.
   */
  public function __invoke(QueryMessage $query): PaginatedResult
  {
    if (!$query instanceof ListUsersQuery) {
      return new PaginatedResult(
        items: [],
        total: 0,
        limit: 0,
        offset: 0
      );
    }

    // Note: UserRepositoryPort needs to support pagination or listing
    // For now, we assume findAll exists or we add it.
    $users = $this->userRepository->findAll();

    return new PaginatedResult(
      items: $users,
      total: count(
        value: $users, 
        mode: COUNT_NORMAL
      ),
      limit: $query->limit,
      offset: ($query->page - 1) * $query->limit
    );
  }
}
