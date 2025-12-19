<?php

declare(strict_types=1);

namespace OAuth\Application\Port\Outbound;

/**
 * Interface ClientValidationPort.
 *
 * Port for validating OAuth2 client credentials.
 * This abstracts the Client module from the Auth module,
 * maintaining proper module isolation in hexagonal architecture.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface ClientValidationPort
{
    // #region Methods
    /**
     * Method validateCredentials.
     *
     * Validates client credentials.
     *
     * @since 1.0.0
     *
     * @param string $clientId     the client ID
     * @param string $clientSecret the client secret
     *
     * @return bool true if credentials are valid, false otherwise
     */
    public function validateCredentials(string $clientId, string $clientSecret): bool;
    // #endregion
}
