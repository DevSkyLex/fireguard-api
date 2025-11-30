<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Symfony\Adapter\Outbound;

use Shared\Application\Port\Outbound\HashingPort;
use Shared\Domain\ValueObject\HashedSecret;

/**
 * Adapter HashingAdapter
 * @final
 *
 * Adapter for hashing and verifying secrets
 * using PHP's password_hash.
 *
 * @category Outbound Adapter
 * @package Shared\Infrastructure\Symfony\Adapter\Outbound
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class HashingAdapter implements HashingPort
{
  //#region Methods
  /**
   * Method hash
   * {@inheritDoc}
   *
   * Hash the provided value using bcrypt.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $value The value to hash.
   *
   * @return HashedSecret The hashed value.
   */
  public function hash(string $value): HashedSecret
  {
    $hashed = password_hash(
      password: $value,
      algo: PASSWORD_BCRYPT
    );

    return new HashedSecret(value: $hashed);
  }

  /**
   * Method verify
   * {@inheritDoc}
   *
   * Verifies that a plain value matches a previously hashed value.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $value The plain value to verify.
   * @param HashedSecret $hashed The hashed value to verify.
   *
   * @return bool True if the values match, false otherwise.
   */
  public function verify(string $value, HashedSecret $hashed): bool
  {
    return password_verify(
      password: $value,
      hash: $hashed->value
    );
  }
  //#endregion
}
