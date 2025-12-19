<?php

declare(strict_types=1);

namespace Shared\Domain\ValueObject;

use Shared\Domain\Exception\InvalidValueException;
use Stringable;

use function preg_match;

/**
 * ValueObject Locale.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class Locale implements Stringable
{
    // #region Constants
    /**
     * Constant PATTERN.
     *
     * The pattern used to validate the locale.
     *
     * @since 1.0.0
     *
     * @var string PATTERN
     */
    private const string PATTERN = '/^[a-z]{2}(?:_[A-Z]{2})?$/';
    // #endregion

    // #region Constructor
    /**
     * Constructor.
     *
     * Initializes a new instance of
     * the Locale class.
     *
     * @since 1.0.0
     *
     * @param string $value the locale
     *
     * @throws InvalidValueException if the locale is invalid
     */
    public function __construct(public string $value)
    {
        if ('' === $value || !preg_match(self::PATTERN, $value)) {
            throw InvalidValueException::because(message: 'Invalid locale provided.');
        }
    }
    // #endregion

    // #region Methods
    /**
     * Method equals.
     *
     * Compares two Locale objects for equality.
     *
     * @since 1.0.0
     *
     * @param self $other the other Locale object to compare
     *
     * @return bool true if the two Locale objects are equal, false otherwise
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Method __toString.
     *
     * Returns the string representation
     * of the Locale object.
     *
     * @since 1.0.0
     *
     * @return string the string representation of the Locale object
     */
    public function __toString(): string
    {
        return $this->value;
    }
    // #endregion
}
