<?php

declare(strict_types=1);

namespace Authorization\Application\Port\Inbound;

use Authorization\Application\Contract\Grant\UserGrantSnapshot;

/**
 * Publishes fresh, scoped grants without relying on cached token or role projections.
 *
 * @category Inbound Port
 */
interface UserGrantSnapshotPort
{
  /**
   * @param string $userId verified current subject
   * @param string|null $tenantId current User-owned tenant fact
   *
   * @return UserGrantSnapshot authoritative current claims
   */
  public function current(string $userId, ?string $tenantId): UserGrantSnapshot;
}
