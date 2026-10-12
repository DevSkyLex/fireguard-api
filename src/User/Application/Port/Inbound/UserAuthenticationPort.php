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
  /**
   * Method authenticate
   *
   * Verifies stored credentials without creation-policy checks. A verification waiver permits only pending accounts.
   *
   * @access public
   *
   * @param string $identifier account email or username
   * @param string $plainPassword submitted existing credential
   * @param bool $requireEmailVerification trusted project authentication choice
   *
   * @return UserAuthentication neutral credential outcome
   */
  public function authenticate(string $identifier, string $plainPassword, bool $requireEmailVerification = true): UserAuthentication;
}
