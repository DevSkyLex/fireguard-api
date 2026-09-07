<?php

declare(strict_types=1);

namespace Auth\Application\Port\Outbound\Federation;

use Auth\Application\Contract\Federation\FederatedConnection;
use Auth\Domain\ValueObject\Federation\FederatedProvider;

/**
 * Interface FederatedIdentityRepositoryPort.
 *
 * Persists external identities in the auth database.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface FederatedIdentityRepositoryPort
{
  public function findBySubject(FederatedProvider $provider, string $subject): ?FederatedConnection;

  public function findForUserProvider(string $userId, FederatedProvider $provider): ?FederatedConnection;

  /**
   * @return list<FederatedConnection>
   */
  public function findForUser(string $userId): array;

  public function save(FederatedConnection $connection): void;

  public function remove(FederatedConnection $connection): void;

  public function removePreservingAccess(FederatedProvider $provider, string $userId, bool $passwordConfigured): bool;
}
