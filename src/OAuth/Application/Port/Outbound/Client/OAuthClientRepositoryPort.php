<?php

declare(strict_types=1);

namespace OAuth\Application\Port\Outbound\Client;

use OAuth\Domain\Model\Client\OAuthClient;
use OAuth\Domain\ValueObject\Client\OAuthClientIdentifier;

/**
 * Interface OAuthClientRepositoryPort.
 *
 * Port for OAuth2 Server client retrieval.
 * This port is used by the OAuth2 Server integration
 * to find clients by their OAuth identifier.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface OAuthClientRepositoryPort
{
  // #region Methods
  /**
   * Method find.
   *
   * Finds a client by its OAuth identifier.
   *
   * @since 1.0.0
   *
   * @param OAuthClientIdentifier $identifier the client identifier
   *
   * @return OAuthClient|null the client or null if not found
   */
  public function find(OAuthClientIdentifier $identifier): ?OAuthClient;
  // #endregion
}
