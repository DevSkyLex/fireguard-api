<?php

declare(strict_types=1);

namespace Otp\Domain\ValueObject;

use function bin2hex;
use function hash_equals;
use function random_bytes;

/**
 * ValueObject ChallengeToken.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ChallengeToken
{
    // #region Properties
    /**
     * Property value.
     *
     * The token value.
     *
     * @since 1.0.0
     */
    public string $value;
    // #endregion

    // #region Constructor
    /**
     * Constructor.
     *
     * @since 1.0.0
     *
     * @param string $value the token value
     */
    private function __construct(string $value)
    {
        $this->value = $value;
    }
    // #endregion

    // #region Factory Methods
    /**
     * Method generate.
     *
     * @static
     *
     * Generates a new random challenge token.
     *
     * @since 1.0.0
     */
    public static function generate(): self
    {
        // Generate a URL-safe random token (32 bytes = 64 hex chars)
        $randomBytes = random_bytes(32);
        $token = bin2hex($randomBytes);

        return new self($token);
    }

    /**
     * Method fromString.
     *
     * @static
     *
     * Creates from an existing token string.
     *
     * @since 1.0.0
     *
     * @param string $token the token string
     */
    public static function fromString(string $token): self
    {
        return new self($token);
    }
    // #endregion

    // #region Methods
    /**
     * Method equals.
     *
     * Checks equality with another token.
     *
     * @since 1.0.0
     *
     * @param self $other the other token
     */
    public function equals(self $other): bool
    {
        return hash_equals($this->value, $other->value);
    }

    /**
     * Method __toString.
     *
     * Returns the token as string.
     *
     * @since 1.0.0
     */
    public function __toString(): string
    {
        return $this->value;
    }
    // #endregion
}
