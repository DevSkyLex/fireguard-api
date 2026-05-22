<?php

declare(strict_types=1);

namespace User\Application\UseCase\Query\User\ListUsers;

use Shared\Application\Contract\Pagination\PaginatedResult;
use User\Application\Port\Outbound\UserRepositoryPort;
use User\Domain\Model\User\User;

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
    $users = $this->userRepository->findFiltered(
      $query->search,
      $query->sorting,
      $query->pagination->limit,
      $query->pagination->offset,
      $query->tenantId,
    );
    $total = $this->userRepository->countFiltered($query->search, $query->tenantId);

    return new PaginatedResult(
      items: $users,
      total: $total,
      limit: $query->pagination->limit,
      offset: $query->pagination->offset,
    );
  }
}
