<?php

declare(strict_types=1);

namespace App\Tests\Architecture\Support;

/**
 * Enum ArchitectureLayer.
 *
 * Enum representing the different layers
 * of the architecture.
 *
 * @category Architecture Support
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum ArchitectureLayer: string
{
    // #region Cases
    /**
     * Case DOMAIN.
     *
     * Domain layer of the
     * architecture.
     */
    case DOMAIN = 'Domain';

    /**
     * Case APPLICATION.
     *
     * Application layer of the
     * architecture.
     */
    case APPLICATION = 'Application';

    /**
     * Case INFRASTRUCTURE.
     *
     * Infrastructure layer of the
     * architecture.
     */
    case INFRASTRUCTURE = 'Infrastructure';
    // #endregion
}
