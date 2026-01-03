<?php

declare(strict_types=1);

namespace OAuth\Application\Port\Outbound\User;

use OAuth\Domain\Model\Oidc\OidcUser;

/**
 * Interface OidcUserProviderPort.
 *
 * Port for retrieving OIDC user identity data.
 *
 * @category Outbound Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface OidcUserProviderPort
{
  // #region Methods
  /**
   * Method findByIdentifier.
   *
   * Finds an OIDC user by identifier.
   *
   * @since 1.0.0
   *
   * @param string $identifier the user identifier
   *
   * @return OidcUser|null the OIDC user or null if not found
   */
  public function findByIdentifier(string $identifier): ?OidcUser;
  // #endregion
}
