<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Domain\ValueObject\Security;

use Auth\Domain\ValueObject\Security\DPoPProof;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

use function time;

/**
 * Test DPoPProofTest.
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
   * Method testFromJwtCreatesProof.
   *
   * Tests that fromJwt builds a proof from valid payload.
   */
  #[Test]
  public function testFromJwtCreatesProof(): void
  {
    $payload = [
      'jti' => 'token-id',
      'htm' => 'GET',
      'htu' => 'https://example.com/resource',
      'iat' => time(),
      'ath' => 'hash',
      'nonce' => 'nonce',
    ];

    $proof = DPoPProof::fromJwt($payload, 'thumbprint');

    $this->assertSame('token-id', $proof->jti);
    $this->assertSame('GET', $proof->htm);
    $this->assertSame('https://example.com/resource', $proof->htu);
    $this->assertInstanceOf(DateTimeImmutable::class, $proof->iat);
    $this->assertSame('thumbprint', $proof->thumbprint);
    $this->assertSame('hash', $proof->ath);
    $this->assertSame('nonce', $proof->nonce);
  }

  /**
   * Method testFromJwtRejectsMissingClaims.
   *
   * Tests that missing required claims throws an exception.
   */
  #[Test]
  public function testFromJwtRejectsMissingClaims(): void
  {
    $this->expectException(InvalidValueException::class);

    DPoPProof::fromJwt(['jti' => 'token-id'], 'thumbprint');
  }

  /**
   * Method testFromJwtRejectsInvalidTypes.
   *
   * Tests that invalid claim types throw an exception.
   */
  #[Test]
  public function testFromJwtRejectsInvalidTypes(): void
  {
    $this->expectException(InvalidValueException::class);

    DPoPProof::fromJwt([
      'jti' => 123,
      'htm' => 'GET',
      'htu' => 'https://example.com',
      'iat' => 'not-int',
    ], 'thumbprint');
  }

  /**
   * Method testIsValidForChecksMethodUriAndAge.
   *
   * Tests that isValidFor validates method, URI, and age.
   */
  #[Test]
  public function testIsValidForChecksMethodUriAndAge(): void
  {
    $proof = new DPoPProof(
      jti: 'token-id',
      htm: 'GET',
      htu: 'https://example.com:443/resource',
      iat: new DateTimeImmutable(),
      thumbprint: 'thumbprint',
    );

    $this->assertTrue($proof->isValidFor('GET', 'https://example.com/resource'));
    $this->assertFalse($proof->isValidFor('POST', 'https://example.com/resource'));
    $this->assertFalse($proof->isValidFor('GET', 'https://example.com/other'));
  }

  /**
   * Method testIsValidForRejectsExpiredProof.
   *
   * Tests that isValidFor returns false when proof is too old.
   */
  #[Test]
  public function testIsValidForRejectsExpiredProof(): void
  {
    $proof = new DPoPProof(
      jti: 'token-id',
      htm: 'GET',
      htu: 'https://example.com/resource',
      iat: new DateTimeImmutable('-1 hour'),
      thumbprint: 'thumbprint',
    );

    $this->assertFalse($proof->isValidFor('GET', 'https://example.com/resource', 10));
  }
  // #endregion
}
