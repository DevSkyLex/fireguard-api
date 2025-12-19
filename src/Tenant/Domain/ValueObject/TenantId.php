<?php

declare(strict_types=1);

namespace Tenant\Domain\ValueObject;

use Shared\Domain\ValueObject\Uuid;

/**
 * ValueObject TenantId.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TenantId extends Uuid
{
    // #region Methods
    /**
     * Method fromString.
     *
     * @static
     *
     * Creates a TenantId from a string value.
     *
     * @since 1.0.0
     *
     * @param string $value the UUID string
     *
     * @return self the created TenantId
     */
    public static function fromString(string $value): self
    {
        return new self(value: $value);
    }
    // #endregion
}
