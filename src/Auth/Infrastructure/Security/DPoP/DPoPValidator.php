<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Security\DPoP;

use Auth\Application\Port\Outbound\DPoPValidatorPort;
use Auth\Domain\ValueObject\Security\DPoPProof;
use Psr\Cache\CacheItemPoolInterface;
use Throwable;

use function array_filter;
use function array_map;
use function array_reverse;
use function array_shift;
use function base64_decode;
use function base64_encode;
use function bin2hex;
use function chr;
use function chunk_split;
use function count;
use function explode;
use function function_exists;
use function hash;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use function ltrim;
use function openssl_verify;
use function ord;
use function pack;
use function random_bytes;
use function rtrim;
use function str_repeat;
use function str_starts_with;
use function strlen;
use function strtr;
use function substr;

use const ARRAY_FILTER_USE_KEY;
use const JSON_UNESCAPED_SLASHES;
use const OPENSSL_ALGO_SHA256;
use const OPENSSL_ALGO_SHA384;
use const OPENSSL_ALGO_SHA512;

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
    private readonly CacheItemPoolInterface $cache,
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
   * @param string $dpopHeader the DPoP header
   * @param string $httpMethod the HTTP method
   * @param string $httpUri the HTTP URI
   * @param string|null $expectedNonce the expected nonce
   * @param string|null $accessToken the access token
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
      $parts = explode('.', $dpopHeader);
      if (3 !== count($parts)) {
        return null;
      }

      $headerJson = $this->base64UrlDecode($parts[0]);
      if (null === $headerJson) {
        return null;
      }

      $header = json_decode($headerJson, true);
      if (!is_array($header) || ($header['typ'] ?? '') !== 'dpop+jwt') {
        return null;
      }

      $alg = $header['alg'] ?? null;
      if (!is_string($alg) || '' === $alg) {
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

      if (!$this->verifySignature($parts, $alg, $jwk)) {
        return null;
      }

      // Parse payload
      $payloadJson = $this->base64UrlDecode($parts[1]);
      if (null === $payloadJson) {
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

      $headerJson = $this->base64UrlDecode($parts[0]);
      if (null === $headerJson) {
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
   * Verifies the DPoP JWT signature against the embedded JWK.
   *
   * @param array<int, string> $parts
   * @param array<string, mixed> $jwk
   */
  private function verifySignature(array $parts, string $alg, array $jwk): bool
  {
    if (!function_exists('openssl_verify')) {
      return false;
    }

    $opensslAlgorithm = $this->mapAlgToOpenSsl($alg);
    if (null === $opensslAlgorithm) {
      return false;
    }

    $signature = $this->base64UrlDecode($parts[2]);
    if (null === $signature) {
      return false;
    }

    $publicKey = $this->jwkToPem($jwk, $alg);
    if (null === $publicKey) {
      return false;
    }

    if (str_starts_with($alg, 'ES')) {
      $signature = $this->joseEcdsaSignatureToDer($signature, $alg);
      if (null === $signature) {
        return false;
      }
    }

    $result = openssl_verify(
      $parts[0] . '.' . $parts[1],
      $signature,
      $publicKey,
      $opensslAlgorithm,
    );

    return 1 === $result;
  }

  /**
   * Converts a base64url string to raw bytes.
   */
  private function base64UrlDecode(string $value): ?string
  {
    $remainder = strlen($value) % 4;
    if (0 !== $remainder) {
      $value .= str_repeat('=', 4 - $remainder);
    }

    $decoded = base64_decode(strtr($value, '-_', '+/'), true);

    return false === $decoded ? null : $decoded;
  }

  /**
   * Maps JWA alg names to OpenSSL algorithms.
   */
  private function mapAlgToOpenSsl(string $alg): ?int
  {
    return match ($alg) {
      'RS256', 'ES256' => OPENSSL_ALGO_SHA256,
      'RS384', 'ES384' => OPENSSL_ALGO_SHA384,
      'RS512', 'ES512' => OPENSSL_ALGO_SHA512,
      default => null,
    };
  }

  /**
   * Converts a JWK public key to PEM.
   *
   * @param array<string, mixed> $jwk
   */
  private function jwkToPem(array $jwk, string $alg): ?string
  {
    $kty = $jwk['kty'] ?? null;

    if ('RSA' === $kty) {
      if (!str_starts_with($alg, 'RS')) {
        return null;
      }

      $n = $jwk['n'] ?? null;
      $e = $jwk['e'] ?? null;
      if (!is_string($n) || !is_string($e)) {
        return null;
      }

      $modulus = $this->base64UrlDecode($n);
      $exponent = $this->base64UrlDecode($e);
      if (null === $modulus || null === $exponent) {
        return null;
      }

      return $this->buildRsaPublicKeyPem($modulus, $exponent);
    }

    if ('EC' === $kty) {
      if (!str_starts_with($alg, 'ES')) {
        return null;
      }

      $curve = $jwk['crv'] ?? null;
      $x = $jwk['x'] ?? null;
      $y = $jwk['y'] ?? null;
      if (!is_string($curve) || !is_string($x) || !is_string($y)) {
        return null;
      }

      $curveInfo = $this->getEcCurveInfo($curve);
      if (null === $curveInfo || $curveInfo['alg'] !== $alg) {
        return null;
      }

      $xBytes = $this->base64UrlDecode($x);
      $yBytes = $this->base64UrlDecode($y);
      if (null === $xBytes || null === $yBytes) {
        return null;
      }

      $xBytes = $this->leftPadToLength($xBytes, $curveInfo['size']);
      $yBytes = $this->leftPadToLength($yBytes, $curveInfo['size']);
      if (null === $xBytes || null === $yBytes) {
        return null;
      }

      return $this->buildEcPublicKeyPem($curveInfo['oid'], $xBytes, $yBytes);
    }

    return null;
  }

  /**
   * Converts a JOSE ECDSA signature (raw) to DER format.
   */
  private function joseEcdsaSignatureToDer(string $signature, string $alg): ?string
  {
    $partLength = match ($alg) {
      'ES256' => 32,
      'ES384' => 48,
      'ES512' => 66,
      default => null,
    };

    if (null === $partLength || strlen($signature) !== $partLength * 2) {
      return null;
    }

    $r = substr($signature, 0, $partLength);
    $s = substr($signature, $partLength);

    $rEncoded = $this->asn1Integer($r);
    $sEncoded = $this->asn1Integer($s);

    return $this->asn1Sequence($rEncoded . $sEncoded);
  }

  /**
   * @return array{oid: string, size: int, alg: string}|null
   */
  private function getEcCurveInfo(string $curve): ?array
  {
    return match ($curve) {
      'P-256' => ['oid' => '1.2.840.10045.3.1.7', 'size' => 32, 'alg' => 'ES256'],
      'P-384' => ['oid' => '1.3.132.0.34', 'size' => 48, 'alg' => 'ES384'],
      'P-521' => ['oid' => '1.3.132.0.35', 'size' => 66, 'alg' => 'ES512'],
      default => null,
    };
  }

  private function leftPadToLength(string $value, int $length): ?string
  {
    if (strlen($value) > $length) {
      return null;
    }

    if (strlen($value) === $length) {
      return $value;
    }

    return str_repeat("\x00", $length - strlen($value)) . $value;
  }

  private function buildRsaPublicKeyPem(string $modulus, string $exponent): string
  {
    $rsaPublicKey = $this->asn1Sequence(
      $this->asn1Integer($modulus) . $this->asn1Integer($exponent),
    );

    $algorithm = $this->asn1Sequence(
      $this->asn1Oid('1.2.840.113549.1.1.1') . "\x05\x00",
    );

    $spki = $this->asn1Sequence($algorithm . $this->asn1BitString($rsaPublicKey));

    return $this->pemEncode('PUBLIC KEY', $spki);
  }

  private function buildEcPublicKeyPem(string $curveOid, string $x, string $y): string
  {
    $publicKey = "\x04" . $x . $y;
    $algorithm = $this->asn1Sequence(
      $this->asn1Oid('1.2.840.10045.2.1') . $this->asn1Oid($curveOid),
    );
    $spki = $this->asn1Sequence($algorithm . $this->asn1BitString($publicKey));

    return $this->pemEncode('PUBLIC KEY', $spki);
  }

  private function pemEncode(string $label, string $der): string
  {
    $body = chunk_split(base64_encode($der), 64, "\n");

    return "-----BEGIN {$label}-----\n{$body}-----END {$label}-----\n";
  }

  private function asn1Sequence(string $data): string
  {
    return "\x30" . $this->asn1Length(strlen($data)) . $data;
  }

  private function asn1Integer(string $data): string
  {
    if ('' === $data) {
      $data = "\x00";
    }

    if (ord($data[0]) > 0x7F) {
      $data = "\x00" . $data;
    }

    return "\x02" . $this->asn1Length(strlen($data)) . $data;
  }

  private function asn1BitString(string $data): string
  {
    return "\x03" . $this->asn1Length(strlen($data) + 1) . "\x00" . $data;
  }

  private function asn1Oid(string $oid): string
  {
    $parts = array_map('intval', explode('.', $oid));
    if (count($parts) < 2) {
      return '';
    }

    $first = array_shift($parts);
    $second = array_shift($parts);
    $encoded = chr((40 * $first) + $second);

    foreach ($parts as $part) {
      $encoded .= $this->base128Encode($part);
    }

    return "\x06" . $this->asn1Length(strlen($encoded)) . $encoded;
  }

  private function base128Encode(int $value): string
  {
    $bytes = [];
    do {
      $bytes[] = $value & 0x7F;
      $value >>= 7;
    } while ($value > 0);

    $bytes = array_reverse($bytes);
    $encoded = '';
    $lastIndex = count($bytes) - 1;
    foreach ($bytes as $index => $byte) {
      if ($index !== $lastIndex) {
        $byte |= 0x80;
      }
      $encoded .= chr($byte);
    }

    return $encoded;
  }

  private function asn1Length(int $length): string
  {
    if ($length < 0x80) {
      return chr($length);
    }

    $lengthBytes = ltrim(pack('N', $length), "\x00");

    return chr(0x80 | strlen($lengthBytes)) . $lengthBytes;
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
