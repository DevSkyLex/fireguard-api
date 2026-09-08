<?php

declare(strict_types=1);

namespace User\Application\Port\Inbound;

use User\Application\Contract\Federation\FederatedUser;

/**
 * Interface FederatedUserPort.
 *
 * Published User-module boundary used by Auth for external identities.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface FederatedUserPort
{
  public function find(string $userId): ?FederatedUser;

  public function provision(string $email, string $firstName, string $lastName): ?FederatedUser;

  public function recordSuccessfulLogin(string $userId): ?FederatedUser;

  public function recordSignInMethod(string $userId, string $method): bool;

  public function setInitialPassword(string $userId, string $plainPassword): bool;
}
