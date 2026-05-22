<?php

declare(strict_types=1);

namespace User\Application\UseCase\Query\User\GetCurrentUserProfile;

use Authorization\Application\Port\Inbound\AuthorizationPort;
use User\Application\Contract\User\UserView;
use User\Application\Port\Outbound\UserRepositoryPort;
use User\Domain\Exception\UserNotFoundException;
use User\Domain\Model\User\User;
use User\Domain\ValueObject\UserId;

use function array_values;

/**
 * Handler GetCurrentUserProfileHandler.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetCurrentUserProfileHandler implements \Shared\Application\Message\QueryHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * GetCurrentUserProfileHandler class.
   *
   * @since 1.0.0
   *
   * @param UserRepositoryPort $userRepository the user repository
   * @param AuthorizationPort $authorization the authorization service port
   */
  public function __construct(
    private UserRepositoryPort $userRepository,
    private AuthorizationPort $authorization,
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
   * @param GetCurrentUserProfileQuery $query the query
   *
   * @throws UserNotFoundException if the authenticated user cannot be resolved
   */
  public function __invoke(GetCurrentUserProfileQuery $query): GetCurrentUserProfileResult
  {
    $user = $this->userRepository->findById(id: new UserId(value: $query->userId));

    if (null === $user) {
      throw UserNotFoundException::withId($query->userId);
    }

    return new GetCurrentUserProfileResult(
      user: $this->mapUser($user),
      roles: array_values($this->authorization->getUserRoleNames($query->userId)),
      permissions: array_values($this->authorization->getUserPermissionNames($query->userId)),
    );
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
    );
  }
  // #endregion
}
