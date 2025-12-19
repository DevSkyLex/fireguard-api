<?php

declare(strict_types=1);

namespace OAuth\Application\Port\Outbound;

/**
 * Interface JwtParserPort.
 *
 * Port for parsing JWT tokens.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface JwtParserPort
{
    // #region Methods
    /**
     * Method parse.
     *
     * Parses a JWT token string.
     *
     * @since 1.0.0
     *
     * @param string $token the token string
     *
     * @return array<string, mixed>|null the token claims or null if invalid
     */
    public function parse(string $token): ?array;
    // #endregion
}
