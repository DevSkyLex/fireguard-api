<?php

declare(strict_types=1);

namespace User\Application\UseCase\Query\User\GetUser;

use User\Application\Contract\User\UserView;
use User\Application\Port\Outbound\UserRepositoryPort;
use User\Domain\Model\User\User;
use User\Domain\ValueObject\UserId;

/**
 * Handler GetUserHandler.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetUserHandler implements \Shared\Application\Message\QueryHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * GetUserHandler class.
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
   * @param GetUserQuery $query the query
   *
   * @return GetUserResult the result
   */
  public function __invoke(GetUserQuery $query): GetUserResult
  {

    $user = $this->userRepository->findById(id: new UserId(value: $query->id));

    if (null === $user) {
      return new GetUserResult(user: null);
    }

    return new GetUserResult(user: $this->mapUser($user));
  }

  /**
   * Method mapUser.
   *
   * Maps a User domain model to UserView.
   *
   * @param User $user the domain user
   *
   * @return UserView the user view
   */
  private function mapUser(User $user): UserView
  {
    return new UserView(
      id: $user->id()->value,
      username: $user->username()->value,
      email: $user->email()->value,
      firstName: $user->profile()->firstName,
      lastName: $user->profile()->lastName,
      avatarUrl: $user->profile()->avatarUrl,
      status: $user->status()->value,
      emailVerified: $user->isEmailVerified(),
      tenantId: $user->tenantId()?->__toString(),
      createdAt: $user->createdAt(),
      lastLoginAt: $user->lastLoginAt(),
      canLogin: $user->canLogin(),
      locale: $user->locale()->value,
    );
  }
  // #endregion
}
