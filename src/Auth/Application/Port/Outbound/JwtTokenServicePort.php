<?php

declare(strict_types=1);

namespace Auth\Application\Port\Outbound;

/**
 * Interface JwtTokenServicePort.
 *
 * Port for JWT token generation and validation.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface JwtTokenServicePort
{
  /**
   * Generate tokens for a user.
   *
   * @param non-empty-string $userId the user ID
   * @param string $email the user email
   * @param array<string> $scopes the granted scopes
   *
   * @return array{access_token: string, refresh_token: string, token_type: string, expires_in: int}
   */
  public function generateTokens(string $userId, string $email, array $scopes = []): array;

  /**
   * Generates a Pre-Auth Token for MFA workflows.
   *
   * @param string $userId the user ID
   * @param string $challengeToken the OTP challenge token associated with this flow
   * @param int $ttl lifetime in seconds (default 300)
   *
   * @return string the valid JWT token
   */
  public function generatePreAuthToken(string $userId, string $challengeToken, int $ttl = 300): string;

  /**
   * Decodes a Pre-Auth Token.
   *
   * @param string $token the JWT token string
   *
   * @return array<string, mixed>|null the payload if valid, null otherwise
   */
  public function decodePreAuthToken(string $token): ?array;

  /**
   * Decode a refresh token.
   *
   * @param string $refreshToken the encrypted refresh token
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
