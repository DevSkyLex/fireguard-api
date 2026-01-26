<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Infrastructure\Security\DPoP;

use Auth\Infrastructure\Security\DPoP\DPoPValidator;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

use function base64_encode;
use function hash;
use function json_encode;
use function rtrim;
use function strtr;
use function time;

use const JSON_UNESCAPED_SLASHES;

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

    $header = [
      'typ' => 'dpop+jwt',
      'jwk' => [
        'kty' => 'RSA',
        'e' => 'AQAB',
        'n' => 'test',
      ],
    ];

    $payload = [
      'jti' => 'jti-unique',
      'htm' => 'GET',
      'htu' => 'https://api.example.com/resource',
      'iat' => time(),
      'nonce' => $nonce,
      'ath' => $this->calculateAccessTokenHash($accessToken),
    ];

    $dpopHeader = $this->buildJwt($header, $payload);

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

    $header = [
      'typ' => 'dpop+jwt',
      'jwk' => [
        'kty' => 'RSA',
        'e' => 'AQAB',
        'n' => 'test',
      ],
    ];

    $encodedHeader = json_encode($header);
    self::assertIsString($encodedHeader);
    $headerPart = $this->base64UrlEncode($encodedHeader);
    $payloadPart = $this->base64UrlEncode('"payload"');

    $token = $this->buildJwtFromParts($headerPart, $payloadPart);

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

    $header = [
      'typ' => 'dpop+jwt',
      'jwk' => [
        'kty' => 'RSA',
        'e' => 'AQAB',
        'n' => 'test',
      ],
    ];

    $payload = [
      'jti' => 'jti-invalid',
      'htm' => 'GET',
      'iat' => time(),
    ];

    $token = $this->buildJwt($header, $payload);

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

    $header = [
      'typ' => 'dpop+jwt',
      'jwk' => [
        'kty' => 'RSA',
        'e' => 'AQAB',
        'n' => 'test',
      ],
    ];

    $payload = [
      'jti' => 'jti-4',
      'htm' => 'GET',
      'htu' => 'https://api.example.com/resource',
      'iat' => time(),
      'nonce' => 'nonce-one',
    ];

    $dpopHeader = $this->buildJwt($header, $payload);

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

    $header = [
      'typ' => 'dpop+jwt',
      'jwk' => [
        'kty' => 'RSA',
        'e' => 'AQAB',
        'n' => 'test',
      ],
    ];

    $payload = [
      'jti' => 'jti-5',
      'htm' => 'GET',
      'htu' => 'https://api.example.com/resource',
      'iat' => time(),
      'ath' => $this->calculateAccessTokenHash('other-token'),
    ];

    $dpopHeader = $this->buildJwt($header, $payload);

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

    $header = [
      'typ' => 'dpop+jwt',
      'jwk' => [
        'kty' => 'RSA',
        'e' => 'AQAB',
        'n' => 'test',
      ],
    ];

    $payload = [
      'jti' => 'jti-6',
      'htm' => 'POST',
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

  private function buildJwtFromParts(string $headerPart, string $payloadPart): string
  {
    return $headerPart . '.' . $payloadPart . '.signature';
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
