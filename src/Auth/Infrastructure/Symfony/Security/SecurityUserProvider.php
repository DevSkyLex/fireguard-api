<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Symfony\Security;

use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Component\Security\Core\Exception\{
  UnsupportedUserException,
  UserNotFoundException
};
use Symfony\Component\Security\Core\User\{
  UserInterface,
  UserProviderInterface
};
use Throwable;
use User\Application\UseCase\Query\GetUser\{
  GetUserQuery,
  GetUserResult
};
use User\Domain\ValueObject\UserStatus;

use function sprintf;

/**
 * Provider SecurityUserProvider
 * @final
 *
 * Symfony Security User Provider.
 * Loads users from the domain layer via the QueryBus.
 *
 * @category Security
 * @package Auth\Infrastructure\Symfony\Security
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements UserProviderInterface<SecurityUser>
 */
final readonly class SecurityUserProvider implements UserProviderInterface
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the SecurityUserProvider class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus The query bus.
   */
  public function __construct(
    private QueryBusPort $queryBus
  ) {
  }
  //#endregion

  //#region Methods
  /**
   * Method loadUserByIdentifier
   * {@inheritDoc}
   *
   * Loads the user for the given user identifier (e.g. email or user ID).
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $identifier The user identifier.
   *
   * @return UserInterface The user.
   *
   * @throws UserNotFoundException If the user is not found.
   */
  public function loadUserByIdentifier(string $identifier): UserInterface
  {
    return $this->loadUserById(userId: $identifier);
  }

  /**
   * Method loadUserById
   *
   * Loads a user by their ID.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $userId The user ID.
   * @param list<string> $scopes Optional OAuth2 scopes.
   *
   * @return SecurityUser The security user.
   *
   * @throws UserNotFoundException If the user is not found.
   */
  public function loadUserById(string $userId, array $scopes = []): SecurityUser
  {
    try {
      /** @var GetUserResult $result */
      $result = $this->queryBus->ask(new GetUserQuery(id: $userId));

      if ($result->user === null) {
        throw new UserNotFoundException(
          message: sprintf('User "%s" not found.', $userId)
        );
      }

      $user = $result->user;

      return new SecurityUser(
        id: $user->id()->value,
        email: (string) $user->email(),
        password: '', // Not needed for token-based auth
        roles: $this->mapStatusToRoles($user->status()),
        scopes: $scopes,
        isActive: $user->status()->canLogin()
      );
    }
    catch (UserNotFoundException $exception) {
      throw $exception;
    }
    catch (Throwable $exception) {
      throw new UserNotFoundException(
        message: sprintf('User "%s" not found: %s', $userId, $exception->getMessage())
      );
    }
  }

  /**
   * Method refreshUser
   * {@inheritDoc}
   *
   * Refreshes the user.
   *
   * @access public
   * @since 1.0.0
   *
   * @param UserInterface $user The user to refresh.
   *
   * @return UserInterface The refreshed user.
   *
   * @throws UnsupportedUserException If the user is not supported.
   */
  public function refreshUser(UserInterface $user): UserInterface
  {
    if (!$user instanceof SecurityUser) {
      throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
    }

    return $this->loadUserById($user->getId(), $user->getScopes());
  }

  /**
   * Method supportsClass
   * {@inheritDoc}
   *
   * Tells Symfony to use this provider for SecurityUser class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $class The class name.
   *
   * @return bool True if the class is supported.
   */
  public function supportsClass(string $class): bool
  {
    return $class === SecurityUser::class;
  }

  /**
   * Method mapStatusToRoles
   *
   * Maps user status to Symfony roles.
   *
   * @access private
   * @since 1.0.0
   *
   * @param UserStatus $status The user status.
   *
   * @return list<string> The roles.
   */
  private function mapStatusToRoles(UserStatus $status): array
  {
    $roles = ['ROLE_USER'];

    if ($status === UserStatus::ACTIVE) {
      $roles[] = 'ROLE_VERIFIED';
    }

    return $roles;
  }
  //#endregion
}
