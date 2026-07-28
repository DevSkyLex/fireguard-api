<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Application\UseCase\Command\Totp\ConfirmTotp;

use Otp\Application\UseCase\Command\Totp\ConfirmTotp\ConfirmTotpResult;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ConfirmTotpResultTest.
 *
 * @category Result Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ConfirmTotpResult::class)]
final class ConfirmTotpResultTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testSuccessFactory(): void
  {
    $result = ConfirmTotpResult::success();

    self::assertTrue($result->success);
    self::assertSame(0, $result->attemptsRemaining);
    self::assertNull($result->error);
  }

  #[Test]
  public function testFailedFactory(): void
  {
    $result = ConfirmTotpResult::failed(2, 'invalid_code');

    self::assertFalse($result->success);
    self::assertSame(2, $result->attemptsRemaining);
    self::assertSame('invalid_code', $result->error);
  }

  #[Test]
  public function testConstructorDefaults(): void
  {
    $result = new ConfirmTotpResult(success: true);

    self::assertTrue($result->success);
    self::assertSame(0, $result->attemptsRemaining);
    self::assertNull($result->error);
  }
  // #endregion
}
