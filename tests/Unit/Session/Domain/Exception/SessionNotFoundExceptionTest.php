<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Domain\Exception;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Session\Domain\Exception\SessionNotFoundException;

/**
 * Test SessionNotFoundExceptionTest.
 *
 * @category Exception Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(SessionNotFoundException::class)]
final class SessionNotFoundExceptionTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testWithIdCreatesMessage(): void
  {
    $exception = SessionNotFoundException::withId('session-2');

    $this->assertStringContainsString('session-2', $exception->getMessage());
  }
  // #endregion
}
