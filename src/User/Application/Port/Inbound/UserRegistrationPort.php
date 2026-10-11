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

  public function register(string $username, string $email, string $password, string $firstName, string $lastName): string;

  public function confirm(string $userId, string $verifiedRecipient): ?UserView;
}
