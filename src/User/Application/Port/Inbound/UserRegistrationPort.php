<?php

declare(strict_types=1);

namespace User\Application\Port\Inbound;

use User\Application\Contract\User\UserView;

/**
 * Owns self-service provisioning and verified-mailbox activation.
 *
 * @category Port
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface UserRegistrationPort
{
  public function emailExists(string $email): bool;

  public function usernameExists(string $username): bool;

  /**
   * Method register
   *
   * Enforces owner password policy; an explicit verification waiver activates without asserting mailbox proof.
   *
   * @access public
   *
   * @param string $username available username
   * @param string $email account email
   * @param string $password proposed credential
   * @param string $firstName profile first name
   * @param string $lastName profile last name
   * @param bool $requireEmailVerification trusted project registration choice
   *
   * @return string created account identity
   */
  public function register(string $username, string $email, string $password, string $firstName, string $lastName, bool $requireEmailVerification = true): string;

  public function confirm(string $userId, string $verifiedRecipient): ?UserView;
}
