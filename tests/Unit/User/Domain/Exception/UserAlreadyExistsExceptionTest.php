<?php

declare(strict_types=1);

namespace Tests\Unit\User\Domain\Exception;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use User\Domain\Exception\UserAlreadyExistsException;

/**
 * Test UserAlreadyExistsExceptionTest.
 *
 * @category Exception Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(UserAlreadyExistsException::class)]
final class UserAlreadyExistsExceptionTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testWithUsernameCreatesMessage(): void
  {
    $exception = UserAlreadyExistsException::withUsername('jdoe');

    $this->assertStringContainsString('jdoe', $exception->getMessage());
  }

  #[Test]
  public function testWithEmailCreatesMessage(): void
  {
    $exception = UserAlreadyExistsException::withEmail('jdoe@example.com');

    $this->assertStringContainsString('jdoe@example.com', $exception->getMessage());
  }
  // #endregion
}
