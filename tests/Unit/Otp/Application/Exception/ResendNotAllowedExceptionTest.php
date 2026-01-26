<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Application\Exception;

use Otp\Application\Exception\ResendNotAllowedException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ResendNotAllowedExceptionTest.
 *
 * @category Exception Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ResendNotAllowedException::class)]
final class ResendNotAllowedExceptionTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testContextAndRetryAfter(): void
  {
    $exception = new ResendNotAllowedException(30);

    self::assertStringContainsString('30', $exception->getMessage());
    self::assertSame(30, $exception->retryAfterSeconds());
    self::assertSame(['retryAfterSeconds' => 30], $exception->context());
  }
  // #endregion
}
