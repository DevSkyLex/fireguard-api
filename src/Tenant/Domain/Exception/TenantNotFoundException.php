<?php

declare(strict_types=1);

namespace Tenant\Domain\Exception;

use Shared\Domain\Exception\EntityNotFoundException;

use function sprintf;

/**
 * Exception TenantNotFoundException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class TenantNotFoundException extends EntityNotFoundException
{
    // #region Methods
    /**
     * Method withId.
     *
     * @static
     *
     * Creates an exception for a missing tenant by ID.
     *
     * @since 1.0.0
     *
     * @param string $id the tenant ID
     *
     * @return self the exception
     */
    public static function withId(string $id): self
    {
        return new self(
            message: sprintf('Tenant with ID "%s" not found.', $id)
        );
    }
    // #endregion
}
