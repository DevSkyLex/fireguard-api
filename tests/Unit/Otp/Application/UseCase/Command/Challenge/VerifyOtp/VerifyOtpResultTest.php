<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Application\UseCase\Command\Challenge\VerifyOtp;

use Otp\Application\UseCase\Command\Challenge\VerifyOtp\VerifyOtpResult;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test VerifyOtpResultTest.
 *
 * @category Result Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(VerifyOtpResult::class)]
final class VerifyOtpResultTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testSuccessFactory(): void
  {
    $result = VerifyOtpResult::success();

    self::assertTrue($result->success);
    self::assertSame(0, $result->attemptsRemaining);
    self::assertNull($result->error);
  }

  #[Test]
  public function testFailedFactory(): void
  {
    $result = VerifyOtpResult::failed(2, 'invalid');

    self::assertFalse($result->success);
    self::assertSame(2, $result->attemptsRemaining);
    self::assertSame('invalid', $result->error);
  }
  // #endregion
}
