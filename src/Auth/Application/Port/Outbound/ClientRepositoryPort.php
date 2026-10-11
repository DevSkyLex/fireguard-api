<?php

declare(strict_types=1);

namespace Auth\Application\Port\Outbound;

use Auth\Application\Contract\Client\ClientRegistration;

/**
 * Interface ClientRepositoryPort.
 *
 * Port for Client retrieval.
 *
 * @category Port
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
   * @param string $identifier the client identifier
   *
   * @return ClientRegistration|null the client or null if not found
   */
  public function find(string $identifier): ?ClientRegistration;
  // #endregion
}
