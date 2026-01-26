<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Domain\ValueObject;

use Otp\Domain\ValueObject\OtpMetadata;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test OtpMetadataTest.
 *
 * @category Value Object Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(OtpMetadata::class)]
final class OtpMetadataTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testFromArraySupportsSnakeCase(): void
  {
    $metadata = OtpMetadata::fromArray([
      'ip_address' => '127.0.0.1',
      'user_agent' => 'Mozilla',
      'device_id' => 'device-1',
    ]);

    self::assertSame('127.0.0.1', $metadata->ipAddress);
    self::assertSame('Mozilla', $metadata->userAgent);
    self::assertSame('device-1', $metadata->deviceId);
  }

  #[Test]
  public function testToArrayAndIsEmpty(): void
  {
    $metadata = OtpMetadata::create(
      ipAddress: '10.0.0.1',
      userAgent: 'TestAgent',
      deviceId: 'device-2',
    );

    self::assertFalse($metadata->isEmpty());

    $data = $metadata->toArray();
    self::assertSame('10.0.0.1', $data['ip_address']);

    $empty = new OtpMetadata();
    self::assertTrue($empty->isEmpty());
  }
  // #endregion
}
