<?php

declare(strict_types=1);

namespace Auth\Application\Port\Outbound;

use Auth\Application\Contract\User\UserAuthenticationResult;

/**
 * Interface UserAuthenticationPort.
 *
 * Port for authenticating a user with credentials.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface UserAuthenticationPort
{
  /**
   * Method authenticate.
   *
   * Authenticates a user by credentials.
   *
   * @since 1.0.0
   *
   * @param string $email the user email or username
   * @param string $password the user password
   *
   * @return UserAuthenticationResult the authentication result
   */
  public function authenticate(string $email, string $password): UserAuthenticationResult;
}
