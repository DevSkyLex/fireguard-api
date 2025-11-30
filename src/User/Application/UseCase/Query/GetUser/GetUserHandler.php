<?php

declare(strict_types=1);

namespace User\Application\UseCase\Query\GetUser;

use Shared\Application\Handler\QueryHandler;
use Shared\Application\Message\QueryMessage;
use Shared\Application\Message\ResultMessage;
use User\Application\Port\Outbound\UserRepositoryPort;
use User\Domain\ValueObject\UserId;

/**
 * Handler GetUserHandler
 * @final
 *
 * Handler for GetUserQuery.
 *
 * @category Handler
 * @package User\Application\UseCase\Query\GetUser
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetUserHandler implements QueryHandler
{
  //#region Constructor
  /**
   * Constructor
   * 
   * Initializes a new instance of the 
   * GetUserHandler class.
   * 
   * @access public
   * @since 1.0.0
   *
   * @param UserRepositoryPort $userRepository The user repository.
   */
  public function __construct(
    private readonly UserRepositoryPort $userRepository
  ) {}
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
   * @return GetUserResult The result.
   */
  public function __invoke(QueryMessage $query): GetUserResult
  {
    if (!$query instanceof GetUserQuery) {
      return new GetUserResult(user: null);
    }

    $user = $this->userRepository->findById(id: new UserId(value: $query->id));

    return new GetUserResult(user: $user);
  }
  //#endregion
}
