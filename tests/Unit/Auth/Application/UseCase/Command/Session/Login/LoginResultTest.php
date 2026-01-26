<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Application\UseCase\Command\Session\Login;

use Auth\Application\UseCase\Command\Session\Login\LoginResult;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test LoginResultTest.
 *
 * @category Result Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(LoginResult::class)]
final class LoginResultTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testFailedFactory(): void
  {
    $result = LoginResult::failed('Invalid login');

    self::assertFalse($result->authenticated);
    self::assertSame('Invalid login', $result->errorMessage);
  }
  // #endregion
}
