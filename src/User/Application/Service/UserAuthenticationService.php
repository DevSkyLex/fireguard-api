<?php

declare(strict_types=1);

namespace User\Application\Service;

use User\Application\Contract\User\UserAuthentication;
use User\Application\Port\Inbound\UserAuthenticationPort;
use User\Application\UseCase\Query\User\AuthenticateUser\{AuthenticateUserHandler, AuthenticateUserQuery};

/**
 * Delegates sign-in to the account-owned authentication handler.
 *
 * @category Service
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UserAuthenticationService implements UserAuthenticationPort
{
  public function __construct(private AuthenticateUserHandler $authenticate)
  {
  }

  /**
   * {@inheritDoc}
   */
  public function authenticate(string $identifier, string $plainPassword): UserAuthentication
  {
    $result = ($this->authenticate)(new AuthenticateUserQuery(username: $identifier, password: $plainPassword));

    return new UserAuthentication($result->authenticated, $result->userId, $result->email);
  }
}
