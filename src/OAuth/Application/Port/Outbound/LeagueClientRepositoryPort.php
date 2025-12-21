<?php

declare(strict_types=1);

namespace OAuth\Application\Port\Outbound;

use OAuth\Domain\Model\LeagueClient;
use OAuth\Domain\ValueObject\OAuthClientIdentifier;

/**
 * Interface LeagueClientRepositoryPort.
 *
 * Port for League OAuth2 Server client retrieval.
 * This port is used by the League OAuth2 Server integration
 * to find clients by their OAuth identifier.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface LeagueClientRepositoryPort
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
   * @return LeagueClient|null the client or null if not found
   */
  public function find(OAuthClientIdentifier $identifier): ?LeagueClient;
  // #endregion
}
