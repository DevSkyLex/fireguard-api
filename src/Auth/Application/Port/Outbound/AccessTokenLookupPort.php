<?php

declare(strict_types=1);

namespace Auth\Application\Port\Outbound;

use Auth\Application\Contract\Token\AccessTokenStatus;

/**
 * Interface AccessTokenLookupPort.
 *
 * Port for retrieving access token status from an external store.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface AccessTokenLookupPort
{
  /**
   * Method find.
   *
   * Finds an access token by its identifier.
   *
   * @since 1.0.0
   *
   * @param string $identifier the token identifier
   *
   * @return AccessTokenStatus|null the token status or null if not found
   */
  public function find(string $identifier): ?AccessTokenStatus;
}
