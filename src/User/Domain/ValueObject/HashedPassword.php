<?php

declare(strict_types=1);

namespace User\Domain\ValueObject;

use Shared\Domain\ValueObject\HashedSecret;

use function password_hash;
use function password_verify;

/**
 * ValueObject HashedPassword.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class HashedPassword extends HashedSecret
{
    // #region Methods
    /**
     * Method fromPlain.
     *
     * @static
     *
     * Creates a HashedPassword from a plain
     * text password.
     *
     * @since 1.0.0
     *
     * @param string $plain the plain text password
     *
     * @return self the hashed password
     */
    public static function fromPlain(string $plain): self
    {
        $hashed = password_hash(
            password: $plain,
            algo: PASSWORD_BCRYPT
        );

        return new self(value: $hashed);
    }

    /**
     * Method verify.
     *
     * Verifies a plain text password against this hashed password.
     *
     * @since 1.0.0
     *
     * @param string $plain the plain text password to verify
     *
     * @return bool true if the password matches, false otherwise
     */
    public function verify(string $plain): bool
    {
        return password_verify(
            password: $plain,
            hash: $this->value
        );
    }
    // #endregion
}
