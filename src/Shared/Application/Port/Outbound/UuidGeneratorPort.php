<?php

declare(strict_types=1);

namespace Shared\Application\Port\Outbound;

/**
 * Port UuidGeneratorPort.
 *
 * Port used to generate UUIDs
 * in the application.
 *
 * @category Outbound Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface UuidGeneratorPort
{
    // #region Methods
    /**
     * Method generate.
     *
     * Generate and return a UUID identifier.
     *
     * @since 1.0.0
     *
     * @return string the generated UUID
     */
    public function generate(): string;
    // #endregion
}
