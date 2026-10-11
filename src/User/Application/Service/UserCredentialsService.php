<?php

declare(strict_types=1);

namespace User\Application\Service;

use User\Application\Contract\User\CredentialAuthentication;
use User\Application\Port\Inbound\{UserCredentialsPort, UserDirectoryPort};
use User\Application\Port\Outbound\Internal\UserRepositoryPort;
use User\Domain\Exception\{InvalidPasswordException, InvalidUserException};
use User\Domain\ValueObject\{HashedPassword, UserId};

/**
 * Keeps account mutation and failed-password accounting inside User.
 *
 * @category Service
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UserCredentialsService implements UserCredentialsPort
{
  /**
   * @param UserRepositoryPort $users the owned repository
   */
  public function __construct(private UserRepositoryPort $users, private UserDirectoryPort $directory)
  {
  }

  /**
   * {@inheritDoc}
   */
  public function authenticate(string $userId, string $plainPassword): CredentialAuthentication
  {
    $user = $this->users->findById(new UserId($userId));
    if (null === $user) {
      return new CredentialAuthentication('missing');
    }

    try {
      $user->authenticate($plainPassword);
    } catch (InvalidPasswordException) {
      $this->users->save($user);

      return new CredentialAuthentication('invalid_password');
    } catch (InvalidUserException) {
      return new CredentialAuthentication('unavailable');
    }
    $this->users->save($user);

    return new CredentialAuthentication('authenticated', $this->directory->findById($userId));
  }

  /**
   * {@inheritDoc}
   */
  public function changePassword(string $userId, string $plainPassword): bool
  {
    $user = $this->users->findById(new UserId($userId));
    if (null === $user) {
      return false;
    }
    $user->changePassword(HashedPassword::fromPlain($plainPassword));
    $this->users->save($user);

    return true;
  }
}
