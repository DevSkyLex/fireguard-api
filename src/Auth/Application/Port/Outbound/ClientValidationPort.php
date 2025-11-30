<?php

declare(strict_types=1);

namespace Auth\Application\Port\Outbound;

/**
 * Interface ClientValidationPort
 *
 * Port for validating OAuth2 client credentials.
 * This abstracts the Client module from the Auth module,
 * maintaining proper module isolation in hexagonal architecture.
 *
 * @category Port
 * @package Auth\Application\Port\Outbound
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface ClientValidationPort
{
  /**
   * Method validateCredentials
   *
   * Validates client credentials.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $clientId The client ID.
   * @param string $clientSecret The client secret.
   *
   * @return bool True if credentials are valid, false otherwise.
   */
  public function validateCredentials(string $clientId, string $clientSecret): bool;
}
