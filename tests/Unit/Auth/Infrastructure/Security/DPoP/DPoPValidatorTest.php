<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Infrastructure\Security\DPoP;

use Auth\Infrastructure\Security\DPoP\DPoPValidator;
use Lcobucci\JWT\Signer\Ecdsa\{Sha256, Sha384, Sha512};
use Lcobucci\JWT\Signer\Key\InMemory;
use LogicException;
use OpenSSLAsymmetricKey;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Psr\Cache\{CacheItemInterface, CacheItemPoolInterface};
use Symfony\Component\Cache\Adapter\ArrayAdapter;

use function base64_decode;
use function base64_encode;
use function hash;
use function json_encode;
use function openssl_pkey_export;
use function openssl_pkey_get_details;
use function openssl_pkey_new;
use function openssl_sign;
use function rtrim;
use function str_repeat;
use function strtr;
use function substr;
use function time;

use const JSON_UNESCAPED_SLASHES;
use const OPENSSL_ALGO_SHA256;
use const OPENSSL_KEYTYPE_EC;
use const OPENSSL_KEYTYPE_RSA;

/**
 * Test DPoPValidatorTest.
 *
 * @category Service Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: DPoPValidator::class)]
final class DPoPValidatorTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testGenerateNonceStoresAndValidates(): void
  {
    $validator = new DPoPValidator(cache: new ArrayAdapter());

    $nonce = $validator->generateNonce();

    self::assertNotEmpty($nonce);
    self::assertTrue($validator->isNonceValid($nonce));
  }

  #[Test]
  public function testCalculateThumbprintFromHeader(): void
  {
    $validator = new DPoPValidator(cache: new ArrayAdapter());

    $header = [
      'typ' => 'dpop+jwt',
      'jwk' => [
        'kty' => 'RSA',
        'e' => 'AQAB',
        'n' => 'test',
      ],
    ];

    $payload = [
      'jti' => 'jti-1',
      'htm' => 'GET',
      'htu' => 'https://api.example.com/resource',
      'iat' => time(),
    ];

    $dpopHeader = $this->buildJwt($header, $payload);

    $thumbprint = $validator->calculateThumbprint($dpopHeader);

    self::assertNotNull($thumbprint);
    self::assertSame($this->calculateJwkThumbprint($header['jwk']), $thumbprint);
  }

  #[Test]
  public function testValidateProofReturnsProofAndEnforcesReplay(): void
  {
    $validator = new DPoPValidator(cache: new ArrayAdapter());

    $accessToken = 'access-token';
    $nonce = $validator->generateNonce();

    [$header, $key] = $this->createRsaHeaderAndKey();

    $payload = [
      'jti' => 'jti-unique',
      'htm' => 'GET',
      'htu' => 'https://api.example.com/resource',
      'iat' => time(),
      'nonce' => $nonce,
      'ath' => $this->calculateAccessTokenHash($accessToken),
    ];

    $dpopHeader = $this->buildSignedJwt($header, $payload, $key);

    $proof = $validator->validateProof(
      dpopHeader: $dpopHeader,
      httpMethod: 'GET',
      httpUri: 'https://api.example.com/resource',
      expectedNonce: $nonce,
      accessToken: $accessToken,
    );

    self::assertNotNull($proof);
    self::assertSame('jti-unique', $proof->jti);

    $second = $validator->validateProof(
      dpopHeader: $dpopHeader,
      httpMethod: 'GET',
      httpUri: 'https://api.example.com/resource',
      expectedNonce: $nonce,
      accessToken: $accessToken,
    );

    self::assertNull($second);
  }

  #[Test]
  public function testValidateProofReturnsNullWhenJwtMalformed(): void
  {
    $validator = new DPoPValidator(cache: new ArrayAdapter());

    self::assertNull($validator->validateProof(
      dpopHeader: 'only.two',
      httpMethod: 'GET',
      httpUri: 'https://api.example.com/resource',
    ));

    self::assertNull($validator->validateProof(
      dpopHeader: 'only.two.parts',
      httpMethod: 'GET',
      httpUri: 'https://api.example.com/resource',
    ));

    $encoded = json_encode([
      'typ' => 'dpop+jwt',
      'jwk' => [
        'kty' => 'RSA',
        'e' => 'AQAB',
        'n' => 'test',
      ],
    ]);
    self::assertIsString($encoded);
    $headerPart = $this->base64UrlEncode($encoded);

    $encodedPayload = json_encode([
      'jti' => 'jti-2',
      'htm' => 'GET',
      'htu' => 'https://api.example.com/resource',
      'iat' => time(),
    ]);
    self::assertIsString($encodedPayload);
    $payloadPart = $this->base64UrlEncode($encodedPayload);

    self::assertNull($validator->validateProof(
      dpopHeader: $this->buildJwtFromParts('###', $payloadPart),
      httpMethod: 'GET',
      httpUri: 'https://api.example.com/resource',
    ));

    self::assertNull($validator->validateProof(
      dpopHeader: $this->buildJwtFromParts($headerPart, '###'),
      httpMethod: 'GET',
      httpUri: 'https://api.example.com/resource',
    ));
  }

  #[Test]
  public function testValidateProofReturnsNullWhenPayloadNotArray(): void
  {
    $validator = new DPoPValidator(cache: new ArrayAdapter());

    [$header, $key] = $this->createRsaHeaderAndKey();

    $encodedHeader = json_encode($header);
    self::assertIsString($encodedHeader);
    $headerPart = $this->base64UrlEncode($encodedHeader);
    $payloadPart = $this->base64UrlEncode('"payload"');

    $token = $this->buildSignedJwtFromParts($headerPart, $payloadPart, $key);

    self::assertNull($validator->validateProof(
      dpopHeader: $token,
      httpMethod: 'GET',
      httpUri: 'https://api.example.com/resource',
    ));
  }

  #[Test]
  public function testValidateProofReturnsNullWhenProofClaimsInvalid(): void
  {
    $validator = new DPoPValidator(cache: new ArrayAdapter());

    [$header, $key] = $this->createRsaHeaderAndKey();

    $payload = [
      'jti' => 'jti-invalid',
      'htm' => 'GET',
      'iat' => time(),
    ];

    $token = $this->buildSignedJwt($header, $payload, $key);

    self::assertNull($validator->validateProof(
      dpopHeader: $token,
      httpMethod: 'GET',
      httpUri: 'https://api.example.com/resource',
    ));
  }

  /**
   * @param array<string, mixed> $header
   */
  #[Test]
  #[DataProvider('invalidHeaderProvider')]
  public function testValidateProofReturnsNullForInvalidHeader(array $header): void
  {
    $validator = new DPoPValidator(cache: new ArrayAdapter());

    $payload = [
      'jti' => 'jti-3',
      'htm' => 'GET',
      'htu' => 'https://api.example.com/resource',
      'iat' => time(),
    ];

    $dpopHeader = $this->buildJwt($header, $payload);

    self::assertNull($validator->validateProof(
      dpopHeader: $dpopHeader,
      httpMethod: 'GET',
      httpUri: 'https://api.example.com/resource',
    ));
  }

  #[Test]
  public function testValidateProofReturnsNullWhenNonceMismatch(): void
  {
    $validator = new DPoPValidator(cache: new ArrayAdapter());

    [$header, $key] = $this->createRsaHeaderAndKey();

    $payload = [
      'jti' => 'jti-4',
      'htm' => 'GET',
      'htu' => 'https://api.example.com/resource',
      'iat' => time(),
      'nonce' => 'nonce-one',
    ];

    $dpopHeader = $this->buildSignedJwt($header, $payload, $key);

    self::assertNull($validator->validateProof(
      dpopHeader: $dpopHeader,
      httpMethod: 'GET',
      httpUri: 'https://api.example.com/resource',
      expectedNonce: 'nonce-two',
    ));
  }

  #[Test]
  public function testValidateProofReturnsNullWhenAthMismatch(): void
  {
    $validator = new DPoPValidator(cache: new ArrayAdapter());

    [$header, $key] = $this->createRsaHeaderAndKey();

    $payload = [
      'jti' => 'jti-5',
      'htm' => 'GET',
      'htu' => 'https://api.example.com/resource',
      'iat' => time(),
      'ath' => $this->calculateAccessTokenHash('other-token'),
    ];

    $dpopHeader = $this->buildSignedJwt($header, $payload, $key);

    self::assertNull($validator->validateProof(
      dpopHeader: $dpopHeader,
      httpMethod: 'GET',
      httpUri: 'https://api.example.com/resource',
      accessToken: 'access-token',
    ));
  }

  #[Test]
  public function testValidateProofReturnsNullWhenProofNotValidForMethod(): void
  {
    $validator = new DPoPValidator(cache: new ArrayAdapter());

    [$header, $key] = $this->createRsaHeaderAndKey();

    $payload = [
      'jti' => 'jti-6',
      'htm' => 'POST',
      'htu' => 'https://api.example.com/resource',
      'iat' => time(),
    ];

    $dpopHeader = $this->buildSignedJwt($header, $payload, $key);

    self::assertNull($validator->validateProof(
      dpopHeader: $dpopHeader,
      httpMethod: 'GET',
      httpUri: 'https://api.example.com/resource',
    ));
  }

  #[Test]
  public function testCalculateThumbprintReturnsNullForInvalidHeader(): void
  {
    $validator = new DPoPValidator(cache: new ArrayAdapter());

    $header = [
      'typ' => 'dpop+jwt',
      'jwk' => [
        'kty' => 'oct',
      ],
    ];

    $payload = [
      'jti' => 'jti-7',
      'htm' => 'GET',
      'htu' => 'https://api.example.com/resource',
      'iat' => time(),
    ];

    $dpopHeader = $this->buildJwt($header, $payload);

    self::assertNull($validator->calculateThumbprint($dpopHeader));
  }

  #[Test]
  public function testCalculateThumbprintReturnsNullForInvalidParts(): void
  {
    $validator = new DPoPValidator(cache: new ArrayAdapter());

    self::assertNull($validator->calculateThumbprint('only.two'));
    self::assertNull($validator->calculateThumbprint('###.payload.signature'));
  }

  #[Test]
  public function testCalculateThumbprintReturnsNullWhenHeaderNotArray(): void
  {
    $validator = new DPoPValidator(cache: new ArrayAdapter());

    $headerPart = $this->base64UrlEncode('"header"');
    $encodedJti = json_encode(['jti' => 'jti-8']);
    self::assertIsString($encodedJti);
    $payloadPart = $this->base64UrlEncode($encodedJti);

    self::assertNull($validator->calculateThumbprint($this->buildJwtFromParts($headerPart, $payloadPart)));
  }

  #[Test]
  public function testCalculateThumbprintReturnsNullWhenJwkNotArray(): void
  {
    $validator = new DPoPValidator(cache: new ArrayAdapter());

    $header = [
      'typ' => 'dpop+jwt',
      'jwk' => 'not-array',
    ];

    $payload = [
      'jti' => 'jti-9',
      'htm' => 'GET',
      'htu' => 'https://api.example.com/resource',
      'iat' => time(),
    ];

    $token = $this->buildJwt($header, $payload);

    self::assertNull($validator->calculateThumbprint($token));
  }

  #[Test]
  public function testCalculateThumbprintSupportsEcKeys(): void
  {
    $validator = new DPoPValidator(cache: new ArrayAdapter());

    $header = [
      'typ' => 'dpop+jwt',
      'jwk' => [
        'kty' => 'EC',
        'crv' => 'P-256',
        'x' => 'test-x',
        'y' => 'test-y',
      ],
    ];

    $payload = [
      'jti' => 'jti-ec',
      'htm' => 'GET',
      'htu' => 'https://api.example.com/resource',
      'iat' => time(),
    ];

    $token = $this->buildJwt($header, $payload);

    self::assertNotNull($validator->calculateThumbprint($token));
  }

  /**
   * Verify real EC signatures, reject tampering and prevent replay after acceptance.
   *
   * @since 1.0.0
   *
   * @param string $curve the OpenSSL curve
   * @param string $jwkCurve the public JWK curve
   * @param string $algorithm the declared signature algorithm
   */
  #[Test]
  #[DataProvider('ecAlgorithmProvider')]
  public function testEcProofVerifiesSignatureAndRejectsReplay(string $curve, string $jwkCurve, string $algorithm): void
  {
    $validator = new DPoPValidator(cache: new ArrayAdapter());
    [$header, $key] = $this->createEcHeaderAndKey($curve, $jwkCurve, $algorithm);
    $payload = ['jti' => 'ec-proof', 'htm' => 'GET', 'htu' => 'https://api.example.com/resource', 'iat' => time()];
    $encodedHeader = json_encode($header);
    $encodedPayload = json_encode($payload);
    self::assertIsString($encodedHeader);
    self::assertIsString($encodedPayload);
    $data = $this->base64UrlEncode($encodedHeader) . '.' . $this->base64UrlEncode($encodedPayload);
    $signer = match ($algorithm) {
      'ES256' => new Sha256(),
      'ES384' => new Sha384(),
      'ES512' => new Sha512(),
      default => throw new LogicException('Unsupported test signature algorithm.'),
    };
    $signature = $signer->sign($data, InMemory::plainText($key));
    $token = $data . '.' . $this->base64UrlEncode($signature);
    $tamperedSignature = $signature;
    $tamperedSignature[0] = $signature[0] ^ "\x01";

    self::assertNull($validator->validateProof($data . '.' . $this->base64UrlEncode($tamperedSignature), 'GET', $payload['htu']));
    self::assertNull($validator->validateProof($data . '.' . $this->base64UrlEncode(substr($signature, 1)), 'GET', $payload['htu']));
    self::assertNull($validator->validateProof($token, 'GET', 'https://api.example.com/different-resource'));

    $proof = $validator->validateProof($token, 'GET', $payload['htu']);

    self::assertNotNull($proof);
    self::assertSame('ec-proof', $proof->jti);
    self::assertSame($validator->calculateThumbprint($token), $proof->thumbprint);
    self::assertNull($validator->validateProof($token, 'GET', $payload['htu']));
  }

  /**
   * Verify fixed public signatures before rejecting their deliberately expired claims.
   *
   * A JOSE integer's redundant leading zero must not survive DER conversion.
   * The replay lookup occurs only after signature verification; expiry must then
   * reject the proof without recording its JTI. No private key or clock mock is used.
   *
   * @since 1.0.0
   *
   * @param string $signature the fixed base64url JOSE signature
   */
  #[Test]
  #[DataProvider('paddedEcSignatureProvider')]
  public function testEcProofWithPaddedJoseIntegerReachesExpiryCheck(string $signature): void
  {
    $header = [
      'typ' => 'dpop+jwt',
      'alg' => 'ES512',
      'jwk' => [
        'kty' => 'EC',
        'crv' => 'P-521',
        'x' => 'ATXbVUNZ6YPEv6DCVHxtKaB3EZNYPtzy8wgzEo1gPvBPdVqUq6MkTwcGZddjiCCuMN8ezLqf_fkXLi4uNiDM7NUi',
        'y' => 'z5DoA7e1KadlTzLbS8qs9RljnC7A2MIf5v3AFY4vfyFN5hW3eAfhI_-UENenAyS-342DqQNmg8VLxnAg9k-A0UY',
      ],
    ];
    $payload = ['jti' => 'der-padding-regression', 'htm' => 'GET', 'htu' => 'https://api.example.com/resource', 'iat' => 0, 'nonce' => 'fixed-fixture-nonce'];
    $encodedHeader = json_encode($header);
    $encodedPayload = json_encode($payload);
    self::assertIsString($encodedHeader);
    self::assertIsString($encodedPayload);
    $data = $this->base64UrlEncode($encodedHeader) . '.' . $this->base64UrlEncode($encodedPayload);
    $rawSignature = base64_decode(strtr($signature, '-_', '+/'), true);
    self::assertIsString($rawSignature);
    $publicKey = <<<'PEM'
      -----BEGIN PUBLIC KEY-----
      MIGbMBAGByqGSM49AgEGBSuBBAAjA4GGAAQBNdtVQ1npg8S/oMJUfG0poHcRk1g+
      3PLzCDMSjWA+8E91WpSroyRPBwZl12OIIK4w3x7Mup/9+RcuLi42IMzs1SIAz5Do
      A7e1KadlTzLbS8qs9RljnC7A2MIf5v3AFY4vfyFN5hW3eAfhI/+UENenAyS+342D
      qQNmg8VLxnAg9k+A0UY=
      -----END PUBLIC KEY-----
      PEM;
    self::assertTrue(new Sha512()->verify($rawSignature, $data, InMemory::plainText($publicKey)));
    self::assertLessThan(time() - 300, $payload['iat']);

    $item = $this->createMock(CacheItemInterface::class);
    $item->expects(self::once())->method('isHit')->willReturn(false);
    $cache = $this->createMock(CacheItemPoolInterface::class);
    $cache->expects(self::once())->method('getItem')->with('dpop_jti_der-padding-regression')->willReturn($item);
    $cache->expects(self::never())->method('save');
    $validator = new DPoPValidator(cache: $cache);

    self::assertNull($validator->validateProof($data . '.' . $signature, 'GET', $payload['htu'], $payload['nonce']));
  }

  /**
   * Supply signatures with redundant unsigned padding in each ECDSA integer.
   *
   * @since 1.0.0
   *
   * @return iterable<string, array{string}>
   */
  public static function paddedEcSignatureProvider(): iterable
  {
    yield 'leading zero in r' => ['ACmW4hW4RNzEr8BpNsAO2KSiLpQHvtsrLGtpDmrrBsZ3f5vxXBZNYp1u4i4swTQjE_ux20_Ap-UiZd3ZnU_ZlNXyAd7UgZ7RwdQ7idV9pcOYpsxXoDCieQXxvtYOr1hMCkGs-45ysPqBp_HxD5i9TRg3TF3T50TXubOslSyzaIBYsHPy'];
    yield 'leading zero in s' => ['AVdxpdmY22WcgS_1aLzi0h-H0r4axDtVV5GXbuZ2aNr6okQZgMzwWNHAuS2lW5X9JD2YUDY9w7QJaEs2KT1aanxMAFr0i24vP_PNJUt02or95yL52_juBYeJdiLrdPE5lijJDx_cl-9z6zqNWMiuHaUrdXzJ_sjguPqn0E5hhW84wU7c'];
  }

  /**
   * Reject unusable public keys and algorithm/curve confusion before accepting claims.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $jwk the malformed public key
   * @param string $algorithm the declared signature algorithm
   */
  #[Test]
  #[DataProvider('invalidEcKeyProvider')]
  public function testEcProofRejectsInvalidKeyAndAlgorithm(array $jwk, string $algorithm): void
  {
    $validator = new DPoPValidator(cache: new ArrayAdapter());
    $header = json_encode(['typ' => 'dpop+jwt', 'alg' => $algorithm, 'jwk' => $jwk]);
    $payload = json_encode(['jti' => 'invalid-ec', 'htm' => 'GET', 'htu' => 'https://api.example.com/resource', 'iat' => time()]);
    self::assertIsString($header);
    self::assertIsString($payload);
    $token = $this->base64UrlEncode($header) . '.' . $this->base64UrlEncode($payload) . '.' . $this->base64UrlEncode(str_repeat("\x01", 64));

    self::assertNull($validator->validateProof($token, 'GET', 'https://api.example.com/resource'));
  }

  /**
   * Exercise the supported EC algorithms with keys generated only in test memory.
   *
   * @since 1.0.0
   *
   * @return iterable<string, array{string, string, string}>
   */
  public static function ecAlgorithmProvider(): iterable
  {
    yield 'P-256 / ES256' => ['prime256v1', 'P-256', 'ES256'];
    yield 'P-384 / ES384' => ['secp384r1', 'P-384', 'ES384'];
    yield 'P-521 / ES512' => ['secp521r1', 'P-521', 'ES512'];
  }

  /**
   * Supply malformed public keys without using production key material.
   *
   * @since 1.0.0
   *
   * @return iterable<string, array{array<string, mixed>, string}>
   */
  public static function invalidEcKeyProvider(): iterable
  {
    $key = ['kty' => 'EC', 'crv' => 'P-256', 'x' => 'AQ', 'y' => 'AQ'];
    yield 'RSA algorithm with EC key' => [$key, 'RS256'];
    yield 'unsupported algorithm' => [$key, 'ES999'];
    yield 'curve and algorithm mismatch' => [$key, 'ES384'];
    yield 'unsupported curve' => [['crv' => 'P-999'] + $key, 'ES256'];
    yield 'missing coordinate' => [['x' => null] + $key, 'ES256'];
    yield 'invalid coordinate encoding' => [['x' => '###'] + $key, 'ES256'];
    yield 'oversized coordinate' => [['x' => str_repeat('A', 48)] + $key, 'ES256'];
  }

  /**
   * @return array<string, array{0: array<string, mixed>}>
   */
  public static function invalidHeaderProvider(): array
  {
    return [
      'missing typ' => [
        [
          'jwk' => [
            'kty' => 'RSA',
            'e' => 'AQAB',
            'n' => 'test',
          ],
        ],
      ],
      'invalid typ' => [
        [
          'typ' => 'JWT',
          'jwk' => [
            'kty' => 'RSA',
            'e' => 'AQAB',
            'n' => 'test',
          ],
        ],
      ],
      'missing jwk' => [
        [
          'typ' => 'dpop+jwt',
        ],
      ],
      'unsupported jwk' => [
        [
          'typ' => 'dpop+jwt',
          'jwk' => [
            'kty' => 'oct',
          ],
        ],
      ],
    ];
  }

  /**
   * Create an ephemeral EC keypair and the corresponding public-only JWK.
   *
   * @since 1.0.0
   *
   * @param string $curve the OpenSSL curve
   * @param string $jwkCurve the JWK curve
   * @param string $algorithm the signature algorithm
   *
   * @return array{array<string, mixed>, non-empty-string}
   */
  private function createEcHeaderAndKey(string $curve, string $jwkCurve, string $algorithm): array
  {
    $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => $curve]);
    self::assertInstanceOf(OpenSSLAsymmetricKey::class, $key);
    $details = openssl_pkey_get_details($key);
    self::assertIsArray($details);
    $coordinates = $details['ec'] ?? null;
    self::assertIsArray($coordinates);
    self::assertIsString($coordinates['x']);
    self::assertIsString($coordinates['y']);
    $privateKey = '';
    self::assertTrue(openssl_pkey_export($key, $privateKey));
    self::assertIsString($privateKey);
    if ('' === $privateKey) {
      throw new LogicException('The test EC key must contain private key material.');
    }

    return [[
      'typ' => 'dpop+jwt',
      'alg' => $algorithm,
      'jwk' => ['kty' => 'EC', 'crv' => $jwkCurve, 'x' => $this->base64UrlEncode($coordinates['x']), 'y' => $this->base64UrlEncode($coordinates['y'])],
    ], $privateKey];
  }

  /**
   * @return array{0: array<string, mixed>, 1: OpenSSLAsymmetricKey}
   */
  private function createRsaHeaderAndKey(): array
  {
    $key = openssl_pkey_new([
      'private_key_bits' => 2048,
      'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);
    self::assertNotFalse($key);
    self::assertInstanceOf(OpenSSLAsymmetricKey::class, $key);
    $details = openssl_pkey_get_details($key);
    self::assertIsArray($details);
    $rsa = $details['rsa'] ?? null;
    self::assertIsArray($rsa);
    $exponent = $rsa['e'];
    $modulus = $rsa['n'];
    self::assertIsString($exponent);
    self::assertIsString($modulus);

    $header = [
      'typ' => 'dpop+jwt',
      'alg' => 'RS256',
      'jwk' => [
        'kty' => 'RSA',
        'e' => $this->base64UrlEncode($exponent),
        'n' => $this->base64UrlEncode($modulus),
      ],
    ];

    return [$header, $key];
  }

  /**
   * @param array<string, mixed> $header
   * @param array<string, mixed> $payload
   */
  private function buildSignedJwt(array $header, array $payload, OpenSSLAsymmetricKey $privateKey): string
  {
    $encodedHeader = json_encode($header);
    $encodedPayload = json_encode($payload);
    self::assertIsString($encodedHeader);
    self::assertIsString($encodedPayload);

    $headerPart = $this->base64UrlEncode($encodedHeader);
    $payloadPart = $this->base64UrlEncode($encodedPayload);
    $data = $headerPart . '.' . $payloadPart;

    $signature = '';
    $result = openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA256);
    self::assertTrue($result);
    self::assertIsString($signature);

    return $data . '.' . $this->base64UrlEncode($signature);
  }

  private function buildJwtFromParts(string $headerPart, string $payloadPart): string
  {
    return $headerPart . '.' . $payloadPart . '.signature';
  }

  private function buildSignedJwtFromParts(
    string $headerPart,
    string $payloadPart,
    OpenSSLAsymmetricKey $privateKey,
  ): string {
    $data = $headerPart . '.' . $payloadPart;
    $signature = '';
    $result = openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA256);
    self::assertTrue($result);
    self::assertIsString($signature);

    return $data . '.' . $this->base64UrlEncode($signature);
  }

  /**
   * @param array<string, mixed> $header
   * @param array<string, mixed> $payload
   */
  private function buildJwt(array $header, array $payload): string
  {
    $encodedHeader = json_encode($header);
    $encodedPayload = json_encode($payload);
    self::assertIsString($encodedHeader);
    self::assertIsString($encodedPayload);

    return $this->base64UrlEncode($encodedHeader)
      . '.' . $this->base64UrlEncode($encodedPayload)
      . '.signature';
  }

  private function base64UrlEncode(string $data): string
  {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
  }

  /**
   * @param array<string, mixed> $jwk
   */
  private function calculateJwkThumbprint(array $jwk): string
  {
    $thumbprintInput = [
      'e' => $jwk['e'] ?? '',
      'kty' => $jwk['kty'] ?? '',
      'n' => $jwk['n'] ?? '',
    ];

    $json = json_encode($thumbprintInput, JSON_UNESCAPED_SLASHES);
    self::assertIsString($json);
    $hash = hash('sha256', $json, true);

    return rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');
  }

  private function calculateAccessTokenHash(string $accessToken): string
  {
    $hash = hash('sha256', $accessToken, true);

    return rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');
  }
  // #endregion
}
