<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Application\UseCase\Query\Token\RefreshToken;

use OAuth\Application\UseCase\Query\Token\RefreshToken\RefreshTokenResult;
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
    $result = RefreshTokenResult::failed('Token invalid');

    self::assertFalse($result->success);
    self::assertSame('Token invalid', $result->errorMessage);
  }
  // #endregion
}
