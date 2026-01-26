<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Domain\ValueObject;

use Otp\Domain\ValueObject\OtpId;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Test OtpIdTest.
 *
 * @category Value Object Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(OtpId::class)]
final class OtpIdTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testCreateWithValidUuid(): void
  {
    $uuid = '550e8400-e29b-41d4-a716-446655440000';
    $otpId = new OtpId($uuid);

    $this->assertSame($uuid, $otpId->value);
  }

  #[Test]
  public function testCreateWithInvalidUuidThrowsException(): void
  {
    $this->expectException(InvalidValueException::class);

    new OtpId('invalid');
  }

  #[Test]
  public function testEquality(): void
  {
    $uuid = '550e8400-e29b-41d4-a716-446655440001';
    $id1 = new OtpId($uuid);
    $id2 = new OtpId($uuid);

    $this->assertTrue($id1->equals($id2));
  }
  // #endregion
}
