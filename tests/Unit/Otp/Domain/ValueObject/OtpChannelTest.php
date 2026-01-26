<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Domain\ValueObject;

use Otp\Domain\ValueObject\OtpChannel;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test OtpChannelTest.
 *
 * @category Value Object Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(OtpChannel::class)]
final class OtpChannelTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testLabelsAndDeliveryFlags(): void
  {
    self::assertSame('Email', OtpChannel::EMAIL->getLabel());
    self::assertTrue(OtpChannel::EMAIL->requiresDelivery());

    self::assertSame('SMS', OtpChannel::SMS->getLabel());
    self::assertTrue(OtpChannel::SMS->requiresDelivery());

    self::assertSame('Authenticator App', OtpChannel::TOTP->getLabel());
    self::assertFalse(OtpChannel::TOTP->requiresDelivery());
  }
  // #endregion
}
