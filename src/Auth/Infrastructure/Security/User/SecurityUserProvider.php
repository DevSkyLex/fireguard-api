<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Security\User;

use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Throwable;
use User\Application\UseCase\Query\GetUser\GetUserQuery;
use User\Application\UseCase\Query\GetUser\GetUserResult;
use User\Domain\ValueObject\UserStatus;

use function sprintf;

/**
 * Provider SecurityUserProvider
 * @final
 *
 * Symfony Security User Provider.
 *
 * @category User
 * @package Auth\Infrastructure\Security\User
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements UserProviderInterface<SecurityUser>
 */
final readonly class SecurityUserProvider implements UserProviderInterface
{
  //#region Constructor
  public function __construct(
    private QueryBusPort $queryBus
  ) {}
  //#endregion

  //#region Methods
  /**
   * {@inheritDoc}
   */
  public function loadUserByIdentifier(string $identifier): UserInterface
  {
    return $this->loadUserById(userId: $identifier);
  }

  /**
   * @param string $userId The user ID.
   * @param list<string> $scopes Optional OAuth2 scopes.
   * @return SecurityUser The security user.
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
        password: '',
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
   * {@inheritDoc}
   */
  public function refreshUser(UserInterface $user): UserInterface
  {
    if (!$user instanceof SecurityUser) {
      throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
    }

    return $this->loadUserById($user->getId(), $user->getScopes());
  }

  /**
   * {@inheritDoc}
   */
  public function supportsClass(string $class): bool
  {
    return $class === SecurityUser::class;
  }

  /**
   * @return list<string>
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
