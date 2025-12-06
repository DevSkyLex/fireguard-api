<?php

declare(strict_types=1);

namespace Auth\Domain\ValueObject;

use DateTimeImmutable;
use Shared\Domain\Exception\InvalidValueException;

/**
 * ValueObject DPoPProof
 * @final
 *
 * Represents a DPoP (Demonstrating Proof of Possession) proof (RFC 9449).
 * DPoP binds tokens to a specific client by requiring the client to prove
 * possession of a private key.
 *
 * @category ValueObject
 * @package Auth\Domain\ValueObject
 * @version 1.0.0
 *
 * @see https://datatracker.ietf.org/doc/html/rfc9449
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DPoPProof
{
  //#region Constructor
  /**
   * Constructor
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $jti Unique identifier for the DPoP proof JWT.
   * @param string $htm HTTP method of the request.
   * @param string $htu HTTP URI of the request.
   * @param DateTimeImmutable $iat Issued at time.
   * @param string $thumbprint JWK thumbprint of the public key.
   * @param string|null $ath Access token hash (optional, for token requests).
   * @param string|null $nonce Server-provided nonce (optional).
   */
  public function __construct(
    public string $jti,
    public string $htm,
    public string $htu,
    public DateTimeImmutable $iat,
    public string $thumbprint,
    public ?string $ath = null,
    public ?string $nonce = null,
  ) {}
  //#endregion

  //#region Methods
  /**
   * Method fromJwt
   * @static
   *
   * Creates a DPoPProof from a decoded JWT payload.
   *
   * @access public
   * @since 1.0.0
   *
   * @param array<string, mixed> $payload The decoded JWT payload.
   * @param string $thumbprint The JWK thumbprint.
   *
   * @return self The DPoP proof.
   *
   * @throws InvalidValueException If the payload is invalid.
   */
  public static function fromJwt(array $payload, string $thumbprint): self
  {
    if (!isset($payload['jti'], $payload['htm'], $payload['htu'], $payload['iat'])) {
      throw InvalidValueException::because('DPoP proof is missing required claims.');
    }

    return new self(
      jti: (string) $payload['jti'],
      htm: (string) $payload['htm'],
      htu: (string) $payload['htu'],
      iat: (new DateTimeImmutable())->setTimestamp((int) $payload['iat']),
      thumbprint: $thumbprint,
      ath: isset($payload['ath']) ? (string) $payload['ath'] : null,
      nonce: isset($payload['nonce']) ? (string) $payload['nonce'] : null,
    );
  }

  /**
   * Method isValidFor
   *
   * Validates the DPoP proof for a specific HTTP method and URI.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $method The expected HTTP method.
   * @param string $uri The expected HTTP URI.
   * @param int $maxAge Maximum age of the proof in seconds.
   *
   * @return bool True if valid, false otherwise.
   */
  public function isValidFor(string $method, string $uri, int $maxAge = 300): bool
  {
    // Check HTTP method
    if (strtoupper($this->htm) !== strtoupper($method)) {
      return false;
    }

    // Check HTTP URI (normalize for comparison)
    if ($this->normalizeUri($this->htu) !== $this->normalizeUri($uri)) {
      return false;
    }

    // Check age
    $age = time() - $this->iat->getTimestamp();
    if ($age < 0 || $age > $maxAge) {
      return false;
    }

    return true;
  }

  /**
   * Method normalizeUri
   *
   * Normalizes a URI for comparison.
   *
   * @access private
   * @since 1.0.0
   *
   * @param string $uri The URI to normalize.
   *
   * @return string The normalized URI.
   */
  private function normalizeUri(string $uri): string
  {
    $parsed = parse_url($uri);

    if ($parsed === false) {
      return $uri;
    }

    $scheme = strtolower($parsed['scheme'] ?? 'https');
    $host = strtolower($parsed['host'] ?? '');
    $port = $parsed['port'] ?? null;
    $path = $parsed['path'] ?? '/';

    // Normalize default ports
    if (($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80)) {
      $port = null;
    }

    $normalized = $scheme . '://' . $host;

    if ($port !== null) {
      $normalized .= ':' . $port;
    }

    $normalized .= $path;

    return $normalized;
  }
  //#endregion
}
