<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Security\DPoP;

use Auth\Application\Port\Outbound\DPoPValidatorPort;
use OAuth\Domain\ValueObject\DPoPProof;
use Psr\Cache\CacheItemPoolInterface;
use Throwable;

use function array_filter;
use function base64_decode;
use function base64_encode;
use function bin2hex;
use function count;
use function explode;
use function hash;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use function random_bytes;
use function rtrim;
use function strtr;

/**
 * Service DPoPValidator.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @see https://datatracker.ietf.org/doc/html/rfc9449
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DPoPValidator implements DPoPValidatorPort
{
  // #region Constants
  /**
   * Constant NONCE_TTL.
   *
   * Nonce cache TTL in seconds.
   *
   * @since 1.0.0
   *
   * @var int
   */
  private const int NONCE_TTL = 300;

  /**
   * Constant MAX_PROOF_AGE.
   *
   * Maximum proof age in seconds.
   *
   * @since 1.0.0
   *
   * @var int
   */
  private const int MAX_PROOF_AGE = 300;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param CacheItemPoolInterface $cache the cache for nonces and JTI tracking
   */
  public function __construct(
    private CacheItemPoolInterface $cache,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method validateProof.
   *
   * Validates a DPoP proof.
   *
   * @since 1.0.0
   *
   * @param string      $dpopHeader    the DPoP header
   * @param string      $httpMethod    the HTTP method
   * @param string      $httpUri       the HTTP URI
   * @param string|null $expectedNonce the expected nonce
   * @param string|null $accessToken   the access token
   *
   * @return DPoPProof|null the DPoP proof or null if invalid
   */
  public function validateProof(
    string $dpopHeader,
    string $httpMethod,
    string $httpUri,
    ?string $expectedNonce = null,
    ?string $accessToken = null,
  ): ?DPoPProof {
    try {
      // Parse the JWT header to extract the public key
      $parts = explode('.', $dpopHeader);
      if (3 !== count($parts)) {
        return null;
      }

      $headerJson = base64_decode(strtr($parts[0], '-_', '+/'), true);
      if (false === $headerJson) {
        return null;
      }

      $header = json_decode($headerJson, true);
      if (!is_array($header) || ($header['typ'] ?? '') !== 'dpop+jwt') {
        return null;
      }

      // Extract public key from JWK
      $jwkRaw = $header['jwk'] ?? null;
      if (!is_array($jwkRaw)) {
        return null;
      }

      // Convert to array<string, mixed>
      /** @var array<string, mixed> $jwk */
      $jwk = array_filter($jwkRaw, fn ($key): bool => is_string($key), ARRAY_FILTER_USE_KEY);

      // Calculate thumbprint
      $thumbprint = $this->calculateJwkThumbprint($jwk);
      if (null === $thumbprint) {
        return null;
      }

      // Parse payload
      $payloadJson = base64_decode(strtr($parts[1], '-_', '+/'), true);
      if (false === $payloadJson) {
        return null;
      }

      $payload = json_decode($payloadJson, true);
      if (!is_array($payload)) {
        return null;
      }

      // Check for replay (JTI uniqueness)
      $jti = $payload['jti'] ?? null;
      if (!is_string($jti) || $this->isJtiUsed($jti)) {
        return null;
      }

      // Validate nonce if expected
      if (null !== $expectedNonce) {
        $proofNonce = $payload['nonce'] ?? null;
        if ($proofNonce !== $expectedNonce) {
          return null;
        }
      }

      // Validate access token hash if provided
      if (null !== $accessToken) {
        $expectedAth = $this->calculateAccessTokenHash($accessToken);
        $proofAth = $payload['ath'] ?? null;
        if ($proofAth !== $expectedAth) {
          return null;
        }
      }

      // Create the proof
      /** @var array<string, mixed> $payload */
      $proof = DPoPProof::fromJwt($payload, $thumbprint);

      // Validate method and URI
      if (!$proof->isValidFor($httpMethod, $httpUri, self::MAX_PROOF_AGE)) {
        return null;
      }

      // Mark JTI as used
      $this->markJtiUsed($jti);

      return $proof;

    } catch (Throwable) {
      return null;
    }
  }

  public function generateNonce(): string
  {
    $nonce = bin2hex(random_bytes(32));

    // Store nonce in cache
    $item = $this->cache->getItem('dpop_nonce_' . $nonce);
    $item->set(true);
    $item->expiresAfter(self::NONCE_TTL);
    $this->cache->save($item);

    return $nonce;
  }

  public function isNonceValid(string $nonce): bool
  {
    $item = $this->cache->getItem('dpop_nonce_' . $nonce);

    return $item->isHit();
  }

  public function calculateThumbprint(string $dpopHeader): ?string
  {
    try {
      $parts = explode('.', $dpopHeader);
      if (3 !== count($parts)) {
        return null;
      }

      $headerJson = base64_decode(strtr($parts[0], '-_', '+/'), true);
      if (false === $headerJson) {
        return null;
      }

      $header = json_decode($headerJson, true);
      if (!is_array($header)) {
        return null;
      }

      $jwkRaw = $header['jwk'] ?? null;
      if (!is_array($jwkRaw)) {
        return null;
      }

      // Convert to array<string, mixed>
      /** @var array<string, mixed> $jwk */
      $jwk = array_filter($jwkRaw, fn ($key): bool => is_string($key), ARRAY_FILTER_USE_KEY);

      return $this->calculateJwkThumbprint($jwk);
    } catch (Throwable) {
      return null;
    }
  }

  /**
   * Method calculateJwkThumbprint.
   *
   * Calculates the JWK thumbprint (RFC 7638).
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $jwk the JWK
   *
   * @return string|null the thumbprint or null if invalid
   */
  private function calculateJwkThumbprint(array $jwk): ?string
  {
    $kty = $jwk['kty'] ?? null;

    if ('RSA' === $kty) {
      $thumbprintInput = [
        'e' => $jwk['e'] ?? '',
        'kty' => $kty,
        'n' => $jwk['n'] ?? '',
      ];
    } elseif ('EC' === $kty) {
      $thumbprintInput = [
        'crv' => $jwk['crv'] ?? '',
        'kty' => $kty,
        'x' => $jwk['x'] ?? '',
        'y' => $jwk['y'] ?? '',
      ];
    } else {
      return null;
    }

    $json = json_encode($thumbprintInput, JSON_UNESCAPED_SLASHES);
    if (false === $json) {
      return null;
    }

    $hash = hash('sha256', $json, true);

    return rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');
  }

  /**
   * Method calculateAccessTokenHash.
   *
   * Calculates the access token hash for the 'ath' claim.
   *
   * @since 1.0.0
   *
   * @param string $accessToken the access token
   *
   * @return string the hash
   */
  private function calculateAccessTokenHash(string $accessToken): string
  {
    $hash = hash('sha256', $accessToken, true);

    return rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');
  }

  /**
   * Method isJtiUsed.
   *
   * Checks if a JTI has been used.
   *
   * @since 1.0.0
   *
   * @param string $jti the JTI
   *
   * @return bool true if used, false otherwise
   */
  private function isJtiUsed(string $jti): bool
  {
    $item = $this->cache->getItem('dpop_jti_' . $jti);

    return $item->isHit();
  }

  /**
   * Method markJtiUsed.
   *
   * Marks a JTI as used.
   *
   * @since 1.0.0
   *
   * @param string $jti the JTI
   */
  private function markJtiUsed(string $jti): void
  {
    $item = $this->cache->getItem('dpop_jti_' . $jti);
    $item->set(true);
    $item->expiresAfter(self::MAX_PROOF_AGE * 2); // Keep longer than max proof age
    $this->cache->save($item);
  }
  // #endregion
}
