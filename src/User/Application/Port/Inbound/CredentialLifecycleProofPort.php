<?php

declare(strict_types=1);

namespace User\Application\Port\Inbound;

use User\Application\Contract\User\CredentialLifecycleProof;

/**
 * Reads current lifecycle facts without mutating credential attempts or trusting an identity map.
 *
 * @category Port
 */
interface CredentialLifecycleProofPort
{
  /**
   * @param string $userId owning account
   */
  public function current(string $userId): ?CredentialLifecycleProof;

  /**
   * @param string $email normalized by the identity owner
   */
  public function byEmail(string $email): ?CredentialLifecycleProof;

  /**
   * @param string $userId owning account
   * @param string $password original proof, never stored in a receipt
   */
  public function verifyPassword(string $userId, string $password): bool;
}
