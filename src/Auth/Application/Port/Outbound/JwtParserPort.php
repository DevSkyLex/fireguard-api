<?php

declare(strict_types=1);

namespace Auth\Application\Port\Outbound;

/**
 * Interface JwtParserPort
 *
 * Port for parsing and validating JWT tokens.
 *
 * @category Port
 * @package Auth\Application\Port\Outbound
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface JwtParserPort
{
  /**
   * Method parse
   *
   * Parses a JWT token and returns its claims.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $token The JWT token.
   *
   * @return array<string, mixed>|null The token claims or null if invalid.
   */
  public function parse(string $token): ?array;

  /**
   * Method validate
   *
   * Validates a JWT token signature and expiration.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $token The JWT token.
   *
   * @return bool True if valid.
   */
  public function validate(string $token): bool;

  /**
   * Method getTokenId
   *
   * Extracts the token ID (jti) from a JWT.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $token The JWT token.
   *
   * @return string|null The token ID or null if not found.
   */
  public function getTokenId(string $token): ?string;

  /**
   * Method getUserId
   *
   * Extracts the user ID (sub) from a JWT.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $token The JWT token.
   *
   * @return string|null The user ID or null if not found.
   */
  public function getUserId(string $token): ?string;
}
