<?php

declare(strict_types=1);

namespace Tests\Unit\User\Domain\Exception;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use User\Domain\Exception\UserNotFoundException;

/**
 * Test UserNotFoundExceptionTest.
 *
 * @category Exception Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(UserNotFoundException::class)]
final class UserNotFoundExceptionTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testWithIdCreatesMessage(): void
  {
    $exception = UserNotFoundException::withId('user-1');

    $this->assertStringContainsString('user-1', $exception->getMessage());
  }

  #[Test]
  public function testWithUsernameCreatesMessage(): void
  {
    $exception = UserNotFoundException::withUsername('jdoe');

    $this->assertStringContainsString('jdoe', $exception->getMessage());
  }

  #[Test]
  public function testWithEmailCreatesMessage(): void
  {
    $exception = UserNotFoundException::withEmail('user@example.com');

    $this->assertStringContainsString('user@example.com', $exception->getMessage());
  }
  // #endregion
}
