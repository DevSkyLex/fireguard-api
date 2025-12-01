<?php

declare(strict_types=1);

namespace Auth\Application\Port\Outbound;

/**
 * Interface JwtTokenServicePort
 *
 * Port for JWT token generation and validation.
 *
 * @category Port
 * @package Auth\Application\Port\Outbound
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface JwtTokenServicePort
{
  /**
   * Generate tokens for a user.
   *
   * @param non-empty-string $userId The user ID.
   * @param string $email The user email.
   * @param array<string> $scopes The granted scopes.
   *
   * @return array{access_token: string, refresh_token: string, token_type: string, expires_in: int}
   */
  public function generateTokens(string $userId, string $email, array $scopes = []): array;

  /**
   * Decode a refresh token.
   *
   * @param string $refreshToken The encrypted refresh token.
   *
   * @return array{refresh_token_id: string, access_token_id: string, user_id: string, scopes: array<string>, expires_at: int}|null
   */
  public function decodeRefreshToken(string $refreshToken): ?array;

  /**
   * Get access token TTL in seconds.
   */
  public function getAccessTokenTtl(): int;

  /**
   * Get refresh token TTL in seconds.
   */
  public function getRefreshTokenTtl(): int;
}
