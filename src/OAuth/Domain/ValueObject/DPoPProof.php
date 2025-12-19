<?php

declare(strict_types=1);

namespace OAuth\Domain\ValueObject;

use DateTimeImmutable;
use Shared\Domain\Exception\InvalidValueException;

use function is_int;
use function is_string;
use function parse_url;
use function strtolower;
use function strtoupper;
use function time;

/**
 * ValueObject DPoPProof.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @see https://datatracker.ietf.org/doc/html/rfc9449
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DPoPProof
{
    // #region Constructor
    /**
     * Constructor.
     *
     * Initialize the DPoP proof
     *
     * @since 1.0.0
     *
     * @param string            $jti        unique identifier for the DPoP proof JWT
     * @param string            $htm        HTTP method of the request
     * @param string            $htu        HTTP URI of the request
     * @param DateTimeImmutable $iat        issued at time
     * @param string            $thumbprint JWK thumbprint of the public key
     * @param string|null       $ath        access token hash (optional, for token requests)
     * @param string|null       $nonce      server-provided nonce (optional)
     */
    public function __construct(
        public string $jti,
        public string $htm,
        public string $htu,
        public DateTimeImmutable $iat,
        public string $thumbprint,
        public ?string $ath = null,
        public ?string $nonce = null,
    ) {
    }
    // #endregion

    // #region Methods
    /**
     * Method fromJwt.
     *
     * @static
     *
     * Creates a DPoPProof from a decoded JWT payload.
     *
     * @since 1.0.0
     *
     * @param array<string, mixed> $payload    the decoded JWT payload
     * @param string               $thumbprint the JWK thumbprint
     *
     * @return self the DPoP proof
     *
     * @throws InvalidValueException if the payload is invalid
     */
    public static function fromJwt(array $payload, string $thumbprint): self
    {
        if (!isset($payload['jti'], $payload['htm'], $payload['htu'], $payload['iat'])) {
            throw InvalidValueException::because('DPoP proof is missing required claims.');
        }

        $jti = $payload['jti'];
        $htm = $payload['htm'];
        $htu = $payload['htu'];
        $iat = $payload['iat'];
        $ath = $payload['ath'] ?? null;
        $nonce = $payload['nonce'] ?? null;

        if (!is_string($jti) || !is_string($htm) || !is_string($htu) || !is_int($iat)) {
            throw InvalidValueException::because('DPoP proof claims have invalid types.');
        }

        return new self(
            jti: $jti,
            htm: $htm,
            htu: $htu,
            iat: (new DateTimeImmutable())->setTimestamp($iat),
            thumbprint: $thumbprint,
            ath: is_string($ath) ? $ath : null,
            nonce: is_string($nonce) ? $nonce : null,
        );
    }

    /**
     * Method isValidFor.
     *
     * Validates the DPoP proof for a specific HTTP method and URI.
     *
     * @since 1.0.0
     *
     * @param string $method the expected HTTP method
     * @param string $uri    the expected HTTP URI
     * @param int    $maxAge maximum age of the proof in seconds
     *
     * @return bool true if valid, false otherwise
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
     * Method normalizeUri.
     *
     * Normalizes a URI for comparison.
     *
     * @since 1.0.0
     *
     * @param string $uri the URI to normalize
     *
     * @return string the normalized URI
     */
    private function normalizeUri(string $uri): string
    {
        $parsed = parse_url($uri);

        if (false === $parsed) {
            return $uri;
        }

        $scheme = strtolower($parsed['scheme'] ?? 'https');
        $host = strtolower($parsed['host'] ?? '');
        $port = $parsed['port'] ?? null;
        $path = $parsed['path'] ?? '/';

        // Normalize default ports
        if (('https' === $scheme && 443 === $port) || ('http' === $scheme && 80 === $port)) {
            $port = null;
        }

        $normalized = $scheme . '://' . $host;

        if (null !== $port) {
            $normalized .= ':' . $port;
        }

        $normalized .= $path;

        return $normalized;
    }
    // #endregion
}
