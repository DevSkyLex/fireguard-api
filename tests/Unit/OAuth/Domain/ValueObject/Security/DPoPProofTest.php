<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Domain\ValueObject\Security;

use DateTimeImmutable;
use OAuth\Domain\ValueObject\Security\DPoPProof;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

use function time;

/**
 * Class DPoPProofTest.
 *
 * Unit tests for the DPoPProof Value Object.
 *
 * @category Unit Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: DPoPProof::class)]
final class DPoPProofTest extends TestCase
{
  // #region Methods
  /**
   * Method testCanCreateDPoPProof.
   *
   * Tests that a DPoPProof can be created.
   */
  #[Test]
  public function testCanCreateDPoPProof(): void
  {
    $now = new DateTimeImmutable();

    $proof = new DPoPProof(
      jti: 'unique-id-123',
      htm: 'POST',
      htu: 'https://auth.example.com/token',
      iat: $now,
      thumbprint: 'jwk-thumbprint-abc',
      ath: 'access-token-hash',
      nonce: 'server-nonce',
    );

    $this->assertEquals(expected: 'unique-id-123', actual: $proof->jti);
    $this->assertEquals(expected: 'POST', actual: $proof->htm);
    $this->assertEquals(expected: 'https://auth.example.com/token', actual: $proof->htu);
    $this->assertEquals(expected: $now, actual: $proof->iat);
    $this->assertEquals(expected: 'jwk-thumbprint-abc', actual: $proof->thumbprint);
    $this->assertEquals(expected: 'access-token-hash', actual: $proof->ath);
    $this->assertEquals(expected: 'server-nonce', actual: $proof->nonce);
  }

  /**
   * Method testFromJwtWithValidPayload.
   *
   * Tests creating a DPoPProof from a valid JWT payload.
   */
  #[Test]
  public function testFromJwtWithValidPayload(): void
  {
    $payload = [
      'jti' => 'jwt-id-456',
      'htm' => 'GET',
      'htu' => 'https://api.example.com/resource',
      'iat' => time(),
      'ath' => 'token-hash',
      'nonce' => 'nonce-value',
    ];

    $proof = DPoPProof::fromJwt($payload, 'thumbprint-xyz');

    $this->assertEquals(expected: 'jwt-id-456', actual: $proof->jti);
    $this->assertEquals(expected: 'GET', actual: $proof->htm);
    $this->assertEquals(expected: 'https://api.example.com/resource', actual: $proof->htu);
    $this->assertEquals(expected: 'thumbprint-xyz', actual: $proof->thumbprint);
    $this->assertEquals(expected: 'token-hash', actual: $proof->ath);
    $this->assertEquals(expected: 'nonce-value', actual: $proof->nonce);
  }

  /**
   * Method testFromJwtWithMissingClaimsThrowsException.
   *
   * Tests that fromJwt throws an exception when required claims are missing.
   */
  #[Test]
  public function testFromJwtWithMissingClaimsThrowsException(): void
  {
    $this->expectException(InvalidValueException::class);
    $this->expectExceptionMessage('DPoP proof is missing required claims.');

    DPoPProof::fromJwt(['jti' => 'id'], 'thumbprint');
  }

  /**
   * Method testIsValidForWithMatchingMethodAndUri.
   *
   * Tests validation with matching HTTP method and URI.
   */
  #[Test]
  public function testIsValidForWithMatchingMethodAndUri(): void
  {
    $proof = new DPoPProof(
      jti: 'id',
      htm: 'POST',
      htu: 'https://auth.example.com/token',
      iat: new DateTimeImmutable(),
      thumbprint: 'thumb',
    );

    $this->assertTrue($proof->isValidFor('POST', 'https://auth.example.com/token'));
    $this->assertTrue($proof->isValidFor('post', 'https://auth.example.com/token')); // Case insensitive method
  }

  /**
   * Method testIsValidForWithMismatchedMethod.
   *
   * Tests validation fails with mismatched HTTP method.
   */
  #[Test]
  public function testIsValidForWithMismatchedMethod(): void
  {
    $proof = new DPoPProof(
      jti: 'id',
      htm: 'POST',
      htu: 'https://auth.example.com/token',
      iat: new DateTimeImmutable(),
      thumbprint: 'thumb',
    );

    $this->assertFalse($proof->isValidFor('GET', 'https://auth.example.com/token'));
  }

  /**
   * Method testIsValidForWithMismatchedUri.
   *
   * Tests validation fails with mismatched URI.
   */
  #[Test]
  public function testIsValidForWithMismatchedUri(): void
  {
    $proof = new DPoPProof(
      jti: 'id',
      htm: 'POST',
      htu: 'https://auth.example.com/token',
      iat: new DateTimeImmutable(),
      thumbprint: 'thumb',
    );

    $this->assertFalse($proof->isValidFor('POST', 'https://auth.example.com/other'));
  }

  /**
   * Method testIsValidForWithExpiredProof.
   *
   * Tests validation fails with an expired proof.
   */
  #[Test]
  public function testIsValidForWithExpiredProof(): void
  {
    $proof = new DPoPProof(
      jti: 'id',
      htm: 'POST',
      htu: 'https://auth.example.com/token',
      iat: new DateTimeImmutable('-10 minutes'), // Older than default 5 minutes
      thumbprint: 'thumb',
    );

    $this->assertFalse($proof->isValidFor('POST', 'https://auth.example.com/token'));
  }

  /**
   * Method testIsValidForWithCustomMaxAge.
   *
   * Tests validation with custom max age.
   */
  #[Test]
  public function testIsValidForWithCustomMaxAge(): void
  {
    $proof = new DPoPProof(
      jti: 'id',
      htm: 'POST',
      htu: 'https://auth.example.com/token',
      iat: new DateTimeImmutable('-10 minutes'),
      thumbprint: 'thumb',
    );

    // Should fail with default 5 minute max age
    $this->assertFalse($proof->isValidFor('POST', 'https://auth.example.com/token', 300));

    // Should pass with 15 minute max age
    $this->assertTrue($proof->isValidFor('POST', 'https://auth.example.com/token', 900));
  }

  /**
   * Method testUriNormalization.
   *
   * Tests that URIs are normalized for comparison.
   */
  #[Test]
  public function testUriNormalization(): void
  {
    $proof = new DPoPProof(
      jti: 'id',
      htm: 'POST',
      htu: 'https://auth.example.com:443/token', // With default HTTPS port
      iat: new DateTimeImmutable(),
      thumbprint: 'thumb',
    );

    // Should match even without explicit port
    $this->assertTrue($proof->isValidFor('POST', 'https://auth.example.com/token'));
  }
  // #endregion
}
