<?php

declare(strict_types=1);

namespace User\Application\UseCase\Query\User\ListUsers;

use Shared\Application\Contract\Pagination\PaginatedResult;
use User\Application\Port\Outbound\UserRepositoryPort;
use User\Domain\Model\User\User;

use function array_slice;
use function count;

use const COUNT_NORMAL;

/**
 * Handler ListUsersHandler.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListUsersHandler implements \Shared\Application\Message\QueryHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * ListUsersHandler class.
   *
   * @since 1.0.0
   *
   * @param UserRepositoryPort $userRepository the user repository
   */
  public function __construct(
    private readonly UserRepositoryPort $userRepository,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Handles the query.
   *
   * @since 1.0.0
   *
   * @param ListUsersQuery $query the query
   *
   * @return PaginatedResult<User> the result
   */
  public function __invoke(ListUsersQuery $query): PaginatedResult
  {

    $users = $this->userRepository->findAll();
    $total = count(value: $users, mode: COUNT_NORMAL);
    $offset = ($query->page - 1) * $query->limit;
    $paged = array_slice($users, $offset, $query->limit);

    return new PaginatedResult(
      items: $paged,
      total: $total,
      limit: $query->limit,
      offset: $offset,
    );
  }
}
