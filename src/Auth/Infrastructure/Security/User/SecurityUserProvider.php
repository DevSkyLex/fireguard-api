<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Security\User;

use Authorization\Application\Port\Inbound\AuthorizationPort;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Throwable;
use User\Application\UseCase\Query\GetUser\GetUserQuery;
use User\Application\UseCase\Query\GetUser\GetUserResult;
use User\Domain\ValueObject\UserStatus;

use function array_map;
use function array_merge;
use function array_unique;
use function array_values;
use function sprintf;
use function strtoupper;

/**
 * Provider SecurityUserProvider.
 *
 * @category User
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements UserProviderInterface<SecurityUser>
 */
final readonly class SecurityUserProvider implements UserProviderInterface
{
  // #region Constructor
  public function __construct(
    private QueryBusPort $queryBus,
    private AuthorizationPort $authorizationService,
  ) {
  }
  // #endregion

  // #region Methods
  public function loadUserByIdentifier(string $identifier): UserInterface
  {
    return $this->loadUserById(userId: $identifier);
  }

  /**
   * @param string       $userId the user ID
   * @param list<string> $scopes optional OAuth2 scopes
   *
   * @return SecurityUser the security user
   *
   * @throws UserNotFoundException if the user is not found
   */
  public function loadUserById(string $userId, array $scopes = []): SecurityUser
  {
    try {
      /** @var GetUserResult $result */
      $result = $this->queryBus->ask(new GetUserQuery(id: $userId));

      if (null === $result->user) {
        throw new UserNotFoundException(
          message: sprintf('User "%s" not found.', $userId)
        );
      }

      $user = $result->user;

      // Get RBAC roles
      $rbacRoles = $this->authorizationService->getUserRoleNames(userId: $user->id()->value);

      // Normalize RBAC roles (e.g. "admin" -> "ROLE_ADMIN")
      $normalizedRbacRoles = array_map(
        fn (string $role) => 'ROLE_' . strtoupper($role),
        $rbacRoles
      );

      // Merge with status-based roles
      $roles = array_values(array_unique(array_merge(
        $this->mapStatusToRoles($user->status()),
        $normalizedRbacRoles
      )));

      return new SecurityUser(
        id: $user->id()->value,
        email: (string) $user->email(),
        password: '',
        roles: $roles,
        scopes: $scopes,
        isActive: $user->status()->canLogin()
      );
    } catch (UserNotFoundException $exception) {
      throw $exception;
    } catch (Throwable $exception) {
      throw new UserNotFoundException(
        message: sprintf('User "%s" not found: %s', $userId, $exception->getMessage())
      );
    }
  }

  public function refreshUser(UserInterface $user): UserInterface
  {
    if (!$user instanceof SecurityUser) {
      throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
    }

    return $this->loadUserById($user->getId(), $user->getScopes());
  }

  public function supportsClass(string $class): bool
  {
    return SecurityUser::class === $class;
  }

  /**
   * @return list<string>
   */
  private function mapStatusToRoles(UserStatus $status): array
  {
    $roles = ['ROLE_USER'];

    if (UserStatus::ACTIVE === $status) {
      $roles[] = 'ROLE_VERIFIED';
    }

    return $roles;
  }
  // #endregion
}
