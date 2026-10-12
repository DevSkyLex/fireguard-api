<?php

declare(strict_types=1);

namespace User\Application\Port\Inbound;

use User\Application\Contract\EmailOwnershipResult;

/**
 * Interface FreshEmailOwnershipPort
 *
 * Exposes uncached current verified email ownership for response disclosure decisions.
 *
 * @category Port
 */
interface FreshEmailOwnershipPort
{
  /**
   * @param string $userId current authenticated account identity
   *
   * @return EmailOwnershipResult current owner scalar facts for an active account
   */
  public function get(string $userId): EmailOwnershipResult;
}
