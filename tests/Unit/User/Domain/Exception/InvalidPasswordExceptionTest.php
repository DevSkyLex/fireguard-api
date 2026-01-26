<?php

declare(strict_types=1);

namespace Tests\Unit\User\Domain\Exception;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use User\Domain\Exception\InvalidPasswordException;

/**
 * Test InvalidPasswordExceptionTest.
 *
 * @category Exception Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InvalidPasswordException::class)]
final class InvalidPasswordExceptionTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testTooWeakCreatesMessage(): void
  {
    $exception = InvalidPasswordException::tooWeak();

    $this->assertStringContainsString('Password is too weak', $exception->getMessage());
  }

  #[Test]
  public function testIncorrectCreatesMessage(): void
  {
    $exception = InvalidPasswordException::incorrect();

    $this->assertSame('Incorrect password.', $exception->getMessage());
  }
  // #endregion
}
