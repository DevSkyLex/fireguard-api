<?php

declare(strict_types=1);

namespace Auth\Application\Port\Outbound;

/**
 * Interface JwtParserPort.
 *
 * Port for parsing and validating JWT tokens.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface JwtParserPort
{
  /**
   * Method parse.
   *
   * Parses a JWT token and returns its claims.
   *
   * @since 1.0.0
   *
   * @param string $token the JWT token
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

  /**
   * Method getTokenId.
   *
   * Extracts the token ID (jti) from a JWT.
   *
   * @since 1.0.0
   *
   * @param string $token the JWT token
   *
   * @return string|null the token ID or null if not found
   */
  public function getTokenId(string $token): ?string;

  /**
   * Method getUserId.
   *
   * Extracts the user ID (sub) from a JWT.
   *
   * @since 1.0.0
   *
   * @param string $token the JWT token
   *
   * @return string|null the user ID or null if not found
   */
  public function getUserId(string $token): ?string;
}
