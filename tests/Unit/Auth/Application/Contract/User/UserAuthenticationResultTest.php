<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Application\Contract\User;

use Auth\Application\Contract\User\UserAuthenticationResult;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test UserAuthenticationResultTest.
 *
 * @category Value Object Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(UserAuthenticationResult::class)]
final class UserAuthenticationResultTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testSuccessFactory(): void
  {
    $result = UserAuthenticationResult::success('user-1', 'user@example.com');

    self::assertTrue($result->authenticated);
    self::assertSame('user-1', $result->userId);
    self::assertSame('user@example.com', $result->email);
  }

  #[Test]
  public function testFailedFactory(): void
  {
    $result = UserAuthenticationResult::failed();

    self::assertFalse($result->authenticated);
    self::assertNull($result->userId);
    self::assertNull($result->email);
  }
  // #endregion
}
