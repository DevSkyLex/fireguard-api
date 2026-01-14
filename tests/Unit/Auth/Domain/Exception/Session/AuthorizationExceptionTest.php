<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Domain\Exception\Session;

use Auth\Domain\Exception\Session\AuthorizationException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test AuthorizationExceptionTest.
 *
 * @category Unit Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: AuthorizationException::class)]
final class AuthorizationExceptionTest extends TestCase
{
  // #region Methods
  /**
   * Method testInvalidClientMessage.
   *
   * Tests invalid client factory message.
   */
  #[Test]
  public function testInvalidClientMessage(): void
  {
    $exception = AuthorizationException::invalidClient();

    $this->assertSame('Invalid client credentials.', $exception->getMessage());
  }

  /**
   * Method testInvalidGrantMessage.
   *
   * Tests invalid grant factory message.
   */
  #[Test]
  public function testInvalidGrantMessage(): void
  {
    $exception = AuthorizationException::invalidGrant('token expired');

    $this->assertSame('Invalid grant: token expired', $exception->getMessage());
  }

  /**
   * Method testInvalidScopeMessage.
   *
   * Tests invalid scope factory message.
   */
  #[Test]
  public function testInvalidScopeMessage(): void
  {
    $exception = AuthorizationException::invalidScope();

    $this->assertSame('Invalid scope requested.', $exception->getMessage());
  }

  /**
   * Method testServerErrorMessage.
   *
   * Tests server error factory message.
   */
  #[Test]
  public function testServerErrorMessage(): void
  {
    $exception = AuthorizationException::serverError('boom');

    $this->assertSame('Authorization server error: boom', $exception->getMessage());
  }
  // #endregion
}
