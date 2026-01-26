<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Domain\ValueObject;

use Otp\Domain\ValueObject\TotpSecret;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Test TotpSecretTest.
 *
 * @category Value Object Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(TotpSecret::class)]
final class TotpSecretTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testGenerateCreatesValidSecret(): void
  {
    $secret = TotpSecret::generate();

    self::assertMatchesRegularExpression('/^[A-Z2-7]+$/', (string) $secret);
  }

  #[Test]
  public function testGetProvisioningUriIncludesSecret(): void
  {
    $secret = new TotpSecret('JBSWY3DPEHPK3PXP');

    $uri = $secret->getProvisioningUri('user@example.com', 'FireGuard Auth');

    self::assertStringContainsString('otpauth://totp/', $uri);
    self::assertStringContainsString('secret=JBSWY3DPEHPK3PXP', $uri);
    self::assertStringContainsString('issuer=FireGuard%20Auth', $uri);
  }

  #[Test]
  public function testInvalidSecretThrowsException(): void
  {
    $this->expectException(InvalidValueException::class);

    new TotpSecret('invalid!');
  }
  // #endregion
}
