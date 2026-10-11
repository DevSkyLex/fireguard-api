<?php

declare(strict_types=1);

namespace User\Application\Port\Inbound;

use User\Application\Contract\User\CredentialAuthentication;

/**
 * Owns password transitions and their failed-attempt state.
 *
 * @category Port
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface UserCredentialsPort
{
  /**
   * @param string $userId the account identifier
   */
  public function authenticate(string $userId, string $plainPassword): CredentialAuthentication;

  /**
   * @param string $userId the account identifier
   */
  public function changePassword(string $userId, string $plainPassword): bool;
}
