<?php

declare(strict_types=1);

namespace Tenant\Domain\ValueObject;

use Shared\Domain\Exception\InvalidValueException;

use function mb_strlen;
use function sprintf;

/**
 * ValueObject TenantName.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TenantName
{
    // #region Constants
    /**
     * Constant MIN_LENGTH.
     *
     * Minimum length for a tenant name.
     *
     * @since 1.0.0
     *
     * @var int
     */
    private const int MIN_LENGTH = 2;
    /**
     * Constant MAX_LENGTH.
     *
     * Maximum length for a tenant name.
     *
     * @since 1.0.0
     *
     * @var int
     */
    private const int MAX_LENGTH = 100;
    // #endregion

    // #region Constructor
    /**
     * Constructor.
     *
     * @since 1.0.0
     *
     * @param string $value the tenant name value
     *
     * @throws InvalidValueException if the name is invalid
     */
    public function __construct(
        public string $value,
    ) {
        $length = mb_strlen($value);

        if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH) {
            throw InvalidValueException::because(
                sprintf(
                    'Tenant name must be between %d and %d characters.',
                    self::MIN_LENGTH,
                    self::MAX_LENGTH
                )
            );
        }
    }
    // #endregion

    // #region Methods
    /**
     * Method __toString.
     *
     * Returns the string representation.
     *
     * @since 1.0.0
     *
     * @return string the tenant name
     */
    public function __toString(): string
    {
        return $this->value;
    }
    // #endregion
}
