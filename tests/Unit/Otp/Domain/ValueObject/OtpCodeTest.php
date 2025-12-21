<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Domain\ValueObject;

use Otp\Domain\ValueObject\OtpCode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

use function strlen;
use function substr;

/**
 * Test OtpCodeTest.
 *
 * @category Unit Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(OtpCode::class)]
final class OtpCodeTest extends TestCase
{
  #[Test]
  public function testGenerateCreates6DigitCode(): void
  {
    $code = OtpCode::generate();

    $plain = $code->plain();

    self::assertMatchesRegularExpression('/^\d{6}$/', $plain);
  }

  #[Test]
  public function testVerifyWithCorrectCode(): void
  {
    $code = OtpCode::generate();
    $plain = $code->plain();

    self::assertTrue($code->verify($plain));
  }

  #[Test]
  public function testVerifyWithIncorrectCode(): void
  {
    $code = OtpCode::generate();

    self::assertFalse($code->verify('000000'));
  }

  #[Test]
  public function testFromHashCanVerify(): void
  {
    $original = OtpCode::generate();
    $plain = $original->plain();
    $hash = $original->hash();

    $recovered = OtpCode::fromHash($hash);

    self::assertTrue($recovered->verify($plain));
  }

  #[Test]
  public function testFromHashPlainNotAvailable(): void
  {
    $original = OtpCode::generate();
    $recovered = OtpCode::fromHash($original->hash());

    $this->expectException(InvalidValueException::class);
    $recovered->plain();
  }

  #[Test]
  public function testMaskedShowsLastTwoDigits(): void
  {
    $code = OtpCode::generate();
    $plain = $code->plain();

    $masked = $code->masked();

    self::assertEquals(6, strlen($masked));
    self::assertEquals(substr($plain, -2), substr($masked, -2));
    self::assertStringStartsWith('****', $masked);
  }
}
