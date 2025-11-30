<?php

declare(strict_types=1);

namespace Auth\Application\Port\Outbound;

use Auth\Domain\Model\Client;
use Shared\Domain\ValueObject\OAuthClientIdentifier;

/**
 * Interface ClientRepositoryPort
 *
 * Port for Client retrieval.
 *
 * @category Port
 * @package Auth\Application\Port\Outbound
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface ClientRepositoryPort
{
  /**
   * Method find
   *
   * Finds a client by its OAuth identifier.
   *
   * @param OAuthClientIdentifier $identifier The client identifier.
   * @return Client|null The client or null if not found.
   */
  public function find(OAuthClientIdentifier $identifier): ?Client;
}
