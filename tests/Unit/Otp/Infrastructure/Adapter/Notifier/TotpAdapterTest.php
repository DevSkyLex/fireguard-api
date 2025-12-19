<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Infrastructure\Adapter\Notifier;

use Otp\Domain\ValueObject\TotpSecret;
use Otp\Infrastructure\Adapter\Notifier\TotpAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Test TotpAdapterTest.
 *
 * @category Unit Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(TotpAdapter::class)]
final class TotpAdapterTest extends TestCase
{
    #[Test]
    public function testGenerateSecretReturnsValidSecret(): void
    {
        $adapter = new TotpAdapter();

        $secret = $adapter->generateSecret();

        self::assertInstanceOf(TotpSecret::class, $secret);
        self::assertMatchesRegularExpression('/^[A-Z2-7]+=*$/', $secret->secret);
    }

    #[Test]
    public function testGetProvisioningUriReturnsValidUri(): void
    {
        $adapter = new TotpAdapter();
        $secret = new TotpSecret('JBSWY3DPEHPK3PXP');

        $uri = $adapter->getProvisioningUri($secret, 'test@example.com');

        self::assertStringStartsWith('otpauth://totp/', $uri);
        self::assertStringContainsString('secret=JBSWY3DPEHPK3PXP', $uri);
        self::assertStringContainsString('issuer=FireGuard%20Auth', $uri);
    }

    #[Test]
    public function testVerifyRejectsInvalidFormat(): void
    {
        $adapter = new TotpAdapter();
        $secret = new TotpSecret('JBSWY3DPEHPK3PXP');

        // Invalid format (not 6 digits)
        self::assertFalse($adapter->verify('123', $secret));
        self::assertFalse($adapter->verify('abcdef', $secret));
        self::assertFalse($adapter->verify('1234567', $secret));
    }
}
