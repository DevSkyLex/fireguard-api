<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Application\Exception;

use Otp\Application\Exception\OtpNotFoundException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test OtpNotFoundExceptionTest.
 *
 * @category Exception Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(OtpNotFoundException::class)]
final class OtpNotFoundExceptionTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testForIdentifierCreatesMessageAndContext(): void
  {
    $exception = OtpNotFoundException::forIdentifier('otp-1');

    self::assertStringContainsString('otp-1', $exception->getMessage());
    self::assertSame(['identifier' => 'otp-1'], $exception->context());
  }
  // #endregion
}
