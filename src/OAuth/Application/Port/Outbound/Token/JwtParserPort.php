<?php

declare(strict_types=1);

namespace OAuth\Application\Port\Outbound\Token;

/**
 * Interface JwtParserPort.
 *
 * Port for parsing JWT tokens.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface JwtParserPort
{
  // #region Methods
  /**
   * Method parse.
   *
   * Parses a JWT token string.
   *
   * @since 1.0.0
   *
   * @param string $token the token string
   *
   * @return array<string, mixed>|null the token claims or null if invalid
   */
  public function parse(string $token): ?array;

  /**
   * Method validate.
   *
   * Validates a JWT token signature and expiration.
   *
   * @since 1.0.0
   *
   * @param string $token the JWT token
   *
   * @return bool true if valid
   */
  public function validate(string $token): bool;
  // #endregion
}
