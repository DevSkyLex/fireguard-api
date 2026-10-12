<?php

declare(strict_types=1);

namespace Shared\Application\Port\Outbound\Idempotency;

use Shared\Application\Contract\Http\HttpIdempotencyAccount;

/**
 * Interface HttpIdempotencyPlatformAuthorityPort
 *
 * Rechecks registered current platform requirements in their owning module.
 *
 * @category Port
 */
interface HttpIdempotencyPlatformAuthorityPort
{
  // #region Methods
  /**
   * @param HttpIdempotencyAccount $account fresh current account facts
   * @param string $key exact API security requirement
   */
  public function permitsKey(HttpIdempotencyAccount $account, string $key): bool;

  /**
   * @param HttpIdempotencyAccount $account fresh current account facts
   * @param string $permission registered required permission
   */
  public function hasPermission(HttpIdempotencyAccount $account, string $permission): bool;

  /**
   * @param HttpIdempotencyAccount $account fresh current account facts
   * @param string $roleName exact required owner role name
   */
  public function hasRole(HttpIdempotencyAccount $account, string $roleName): bool;
  // #endregion
}
