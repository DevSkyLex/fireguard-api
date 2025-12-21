<?php

declare(strict_types=1);

namespace Auth\Application\Port\Outbound;

use Auth\Domain\Model\Client;
use OAuth\Domain\ValueObject\OAuthClientIdentifier;

/**
 * Interface ClientRepositoryPort.
 *
 * Port for Client retrieval.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface ClientRepositoryPort
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
   * @return Client|null the client or null if not found
   */
  public function find(OAuthClientIdentifier $identifier): ?Client;
  // #endregion
}
