<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Application\UseCase\Query\Session\RefreshToken;

use Auth\Application\UseCase\Query\Session\RefreshToken\RefreshTokenResult;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test RefreshTokenResultTest.
 *
 * @category Result Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(RefreshTokenResult::class)]
final class RefreshTokenResultTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testFailedFactory(): void
  {
    $result = RefreshTokenResult::failed('Token expired');

    self::assertFalse($result->success);
    self::assertSame('Token expired', $result->errorMessage);
  }
  // #endregion
}
