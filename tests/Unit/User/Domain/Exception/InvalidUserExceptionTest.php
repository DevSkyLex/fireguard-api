<?php

declare(strict_types=1);

namespace Tests\Unit\User\Domain\Exception;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use User\Domain\Exception\InvalidUserException;

/**
 * Test InvalidUserExceptionTest.
 *
 * @category Exception Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InvalidUserException::class)]
final class InvalidUserExceptionTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testLockedAccountCreatesMessage(): void
  {
    $exception = InvalidUserException::lockedAccount();

    $this->assertSame('User account is locked.', $exception->getMessage());
  }

  #[Test]
  public function testEmailNotVerifiedCreatesMessage(): void
  {
    $exception = InvalidUserException::emailNotVerified();

    $this->assertSame('User email is not verified.', $exception->getMessage());
  }

  #[Test]
  public function testCannotLoginIncludesReason(): void
  {
    $exception = InvalidUserException::cannotLogin('Inactive');

    $this->assertStringContainsString('Inactive', $exception->getMessage());
  }
  // #endregion
}
