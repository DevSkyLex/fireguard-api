<?php

declare(strict_types=1);

namespace OAuth\Application\Port\Outbound;

use OAuth\Domain\Model\RefreshToken;

/**
 * Interface RefreshTokenRepositoryPort.
 *
 * Port for Refresh Token repository.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface RefreshTokenRepositoryPort
{
    // #region Methods
    /**
     * Method save.
     *
     * Saves a refresh token.
     *
     * @since 1.0.0
     *
     * @param RefreshToken $refreshToken the refresh token to save
     */
    public function save(RefreshToken $refreshToken): void;

    /**
     * Method find.
     *
     * Finds a refresh token by identifier.
     *
     * @since 1.0.0
     *
     * @param string $identifier the refresh token identifier
     *
     * @return RefreshToken|null the refresh token or null if not found
     */
    public function find(string $identifier): ?RefreshToken;
    // #endregion
}
