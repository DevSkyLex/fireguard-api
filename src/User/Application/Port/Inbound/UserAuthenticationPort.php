<?php

declare(strict_types=1);

namespace User\Application\Port\Inbound;

use User\Application\Contract\User\UserAuthentication;

/**
 * Owns sign-in credential verification.
 *
 * @category Port
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface UserAuthenticationPort
{
  public function authenticate(string $identifier, string $plainPassword): UserAuthentication;
}
