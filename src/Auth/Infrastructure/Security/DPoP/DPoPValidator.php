<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Security\DPoP;

use Auth\Application\Port\Outbound\DPoPValidatorPort;
use Auth\Domain\ValueObject\DPoPProof;
use DateTimeImmutable;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Plain;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Psr\Cache\CacheItemPoolInterface;
use Throwable;

/**
 * Service DPoPValidator
 * @final
 *
 * Validates DPoP (Demonstrating Proof of Possession) proofs (RFC 9449).
 *
 * @category Service
 * @package Auth\Infrastructure\Security\DPoP
 * @version 1.0.0
 *
 * @see https://datatracker.ietf.org/doc/html/rfc9449
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DPoPValidator implements DPoPValidatorPort
{
  //#region Properties
  /**
   * Nonce cache TTL in seconds.
   */
  private const int NONCE_TTL = 300;

  /**
   * Maximum proof age in seconds.
   */
  private const int MAX_PROOF_AGE = 300;
  //#endregion

  //#region Constructor
  /**
   * Constructor
   *
   * @access public
   * @since 1.0.0
   *
   * @param CacheItemPoolInterface $cache The cache for nonces and JTI tracking.
   */
  public function __construct(
    private CacheItemPoolInterface $cache,
  ) {}
  //#endregion

  //#region Methods
  /**
   * {@inheritDoc}
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
      if (count($parts) !== 3) {
        return null;
      }

      $headerJson = base64_decode(strtr($parts[0], '-_', '+/'), true);
      if ($headerJson === false) {
        return null;
      }

      $header = json_decode($headerJson, true);
      if (!is_array($header) || ($header['typ'] ?? '') !== 'dpop+jwt') {
        return null;
      }

      // Extract public key from JWK
      $jwk = $header['jwk'] ?? null;
      if (!is_array($jwk)) {
        return null;
      }

      // Calculate thumbprint
      $thumbprint = $this->calculateJwkThumbprint($jwk);
      if ($thumbprint === null) {
        return null;
      }

      // Parse payload
      $payloadJson = base64_decode(strtr($parts[1], '-_', '+/'), true);
      if ($payloadJson === false) {
        return null;
      }

      $payload = json_decode($payloadJson, true);
      if (!is_array($payload)) {
        return null;
      }

      // Check for replay (JTI uniqueness)
      $jti = $payload['jti'] ?? null;
      if ($jti === null || $this->isJtiUsed($jti)) {
        return null;
      }

      // Validate nonce if expected
      if ($expectedNonce !== null) {
        $proofNonce = $payload['nonce'] ?? null;
        if ($proofNonce !== $expectedNonce) {
          return null;
        }
      }

      // Validate access token hash if provided
      if ($accessToken !== null) {
        $expectedAth = $this->calculateAccessTokenHash($accessToken);
        $proofAth = $payload['ath'] ?? null;
        if ($proofAth !== $expectedAth) {
          return null;
        }
      }

      // Create the proof
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

  /**
   * {@inheritDoc}
   */
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

  /**
   * {@inheritDoc}
   */
  public function isNonceValid(string $nonce): bool
  {
    $item = $this->cache->getItem('dpop_nonce_' . $nonce);
    return $item->isHit();
  }

  /**
   * {@inheritDoc}
   */
  public function calculateThumbprint(string $dpopHeader): ?string
  {
    try {
      $parts = explode('.', $dpopHeader);
      if (count($parts) !== 3) {
        return null;
      }

      $headerJson = base64_decode(strtr($parts[0], '-_', '+/'), true);
      if ($headerJson === false) {
        return null;
      }

      $header = json_decode($headerJson, true);
      if (!is_array($header)) {
        return null;
      }

      $jwk = $header['jwk'] ?? null;
      if (!is_array($jwk)) {
        return null;
      }

      return $this->calculateJwkThumbprint($jwk);
    } catch (Throwable) {
      return null;
    }
  }

  /**
   * Method calculateJwkThumbprint
   *
   * Calculates the JWK thumbprint (RFC 7638).
   *
   * @access private
   * @since 1.0.0
   *
   * @param array<string, mixed> $jwk The JWK.
   *
   * @return string|null The thumbprint or null if invalid.
   */
  private function calculateJwkThumbprint(array $jwk): ?string
  {
    $kty = $jwk['kty'] ?? null;

    if ($kty === 'RSA') {
      $thumbprintInput = [
        'e' => $jwk['e'] ?? '',
        'kty' => $kty,
        'n' => $jwk['n'] ?? '',
      ];
    } elseif ($kty === 'EC') {
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
    if ($json === false) {
      return null;
    }

    $hash = hash('sha256', $json, true);
    return rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');
  }

  /**
   * Method calculateAccessTokenHash
   *
   * Calculates the access token hash for the 'ath' claim.
   *
   * @access private
   * @since 1.0.0
   *
   * @param string $accessToken The access token.
   *
   * @return string The hash.
   */
  private function calculateAccessTokenHash(string $accessToken): string
  {
    $hash = hash('sha256', $accessToken, true);
    return rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');
  }

  /**
   * Method isJtiUsed
   *
   * Checks if a JTI has been used.
   *
   * @access private
   * @since 1.0.0
   *
   * @param string $jti The JTI.
   *
   * @return bool True if used, false otherwise.
   */
  private function isJtiUsed(string $jti): bool
  {
    $item = $this->cache->getItem('dpop_jti_' . $jti);
    return $item->isHit();
  }

  /**
   * Method markJtiUsed
   *
   * Marks a JTI as used.
   *
   * @access private
   * @since 1.0.0
   *
   * @param string $jti The JTI.
   *
   * @return void
   */
  private function markJtiUsed(string $jti): void
  {
    $item = $this->cache->getItem('dpop_jti_' . $jti);
    $item->set(true);
    $item->expiresAfter(self::MAX_PROOF_AGE * 2); // Keep longer than max proof age
    $this->cache->save($item);
  }
  //#endregion
}
