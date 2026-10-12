<?php

declare(strict_types=1);

namespace User\Application\Service;

use User\Application\Contract\User\CredentialAuthentication;
use User\Application\Port\Inbound\{UserCredentialsPort, UserDirectoryPort};
use User\Application\Port\Outbound\Internal\UserRepositoryPort;
use User\Application\Port\Outbound\PasswordPolicyPort;
use User\Domain\Exception\{InvalidPasswordException, InvalidUserException};
use User\Domain\Model\User\User;
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
  public function __construct(private UserRepositoryPort $users, private UserDirectoryPort $directory, private PasswordPolicyPort $passwordPolicy)
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

    return $this->authenticateAccount($user, $userId, $plainPassword);
  }

  /**
   * {@inheritDoc}
   */
  public function changePassword(string $userId, string $plainPassword): bool
  {
    $this->passwordPolicy->assertAcceptable($plainPassword);

    $user = $this->users->findById(new UserId($userId));
    if (null === $user) {
      return false;
    }
    $user->changePassword(HashedPassword::fromPlain($plainPassword));
    $this->users->save($user);

    return true;
  }

  /**
   * Method authenticateAccount.
   *
   * Authenticates a resolved account and persists its failed-password accounting.
   *
   * @access private
   * @since 1.0.0
   *
   * @param User $user the owned account
   * @param string $userId the requested account identifier
   * @param string $plainPassword the password to verify
   *
   * @return CredentialAuthentication the authentication outcome
   */
  private function authenticateAccount(User $user, string $userId, string $plainPassword): CredentialAuthentication
  {
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
}
