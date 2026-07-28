<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Application\UseCase\Command\Totp\DisableTotp;

use Otp\Application\UseCase\Command\Totp\DisableTotp\DisableTotpResult;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test DisableTotpResultTest.
 *
 * @category Result Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(DisableTotpResult::class)]
final class DisableTotpResultTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testSuccessFactory(): void
  {
    $result = DisableTotpResult::success();

    self::assertTrue($result->success);
    self::assertNull($result->error);
  }

  #[Test]
  public function testFailedFactory(): void
  {
    $result = DisableTotpResult::failed('not_enrolled');

    self::assertFalse($result->success);
    self::assertSame('not_enrolled', $result->error);
  }

  #[Test]
  public function testConstructorDefaults(): void
  {
    $result = new DisableTotpResult(success: false);

    self::assertFalse($result->success);
    self::assertNull($result->error);
  }
  // #endregion
}
