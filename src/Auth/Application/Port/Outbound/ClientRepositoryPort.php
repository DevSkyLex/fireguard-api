<?php

declare(strict_types=1);

namespace Auth\Application\Port\Outbound;

use Auth\Domain\Model\Client\Client;
use Auth\Domain\ValueObject\Client\ClientIdentifier;

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
   * Finds a client by its identifier.
   *
   * @since 1.0.0
   *
   * @param ClientIdentifier $identifier the client identifier
   *
   * @return Client|null the client or null if not found
   */
  public function find(ClientIdentifier $identifier): ?Client;
  // #endregion
}
