<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Symfony\Adapter\Outbound;

use Shared\Application\Port\Outbound\HashingPort;
use Shared\Domain\ValueObject\HashedSecret;

use function password_hash;
use function password_verify;

/**
 * Adapter HashingAdapter.
 *
 * @category Outbound Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class HashingAdapter implements HashingPort
{
  // #region Methods
  /**
   * Method hash
   * {@inheritDoc}
   *
   * Hash the provided value using bcrypt.
   *
   * @since 1.0.0
   *
   * @param string $value the value to hash
   *
   * @return HashedSecret the hashed value
   */
  public function hash(string $value): HashedSecret
  {
    $hashed = password_hash(
      password: $value,
      algo: PASSWORD_BCRYPT,
    );

    return new HashedSecret(value: $hashed);
  }

  /**
   * Method verify
   * {@inheritDoc}
   *
   * Verifies that a plain value matches a previously hashed value.
   *
   * @since 1.0.0
   *
   * @param string $value the plain value to verify
   * @param HashedSecret $hashed the hashed value to verify
   *
   * @return bool true if the values match, false otherwise
   */
  public function verify(string $value, HashedSecret $hashed): bool
  {
    return password_verify(
      password: $value,
      hash: $hashed->value,
    );
  }
  // #endregion
}
