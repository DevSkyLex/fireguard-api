<?php

declare(strict_types=1);

namespace Shared\Domain\ValueObject;

use Shared\Domain\Exception\InvalidValueException;
use Stringable;

use function str_starts_with;

/**
 * ValueObject HashedSecret.
 *
 * It represents a hashed secret.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
readonly class HashedSecret implements Stringable
{
    // #region Constructor
    /**
     * Constructor.
     *
     * Initializes a new instance of
     * the HashedSecret class.
     *
     * @since 1.0.0
     *
     * @param string $value the hashed secret
     *
     * @throws InvalidValueException if the hashed secret is invalid
     */
    public function __construct(public string $value)
    {
        if ('' === $value || false === str_starts_with($value, '$')) {
            throw InvalidValueException::because(message: 'Invalid hashed secret.');
        }
    }
    // #endregion

    // #region Methods
    /**
     * Method equals.
     *
     * @since 1.0.0
     *
     * @param self $other the other hashed secret to compare
     *
     * @return bool true if the hashed secrets are equal, false otherwise
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Method __toString.
     *
     * @since 1.0.0
     *
     * @return string the hashed secret as a string
     */
    public function __toString(): string
    {
        return $this->value;
    }
    // #endregion
}
