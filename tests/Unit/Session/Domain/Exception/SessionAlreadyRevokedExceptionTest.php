<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Domain\Exception;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Session\Domain\Exception\SessionAlreadyRevokedException;

/**
 * Test SessionAlreadyRevokedExceptionTest.
 *
 * @category Exception Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(SessionAlreadyRevokedException::class)]
final class SessionAlreadyRevokedExceptionTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testWithIdCreatesMessage(): void
  {
    $exception = SessionAlreadyRevokedException::withId('session-1');

    $this->assertStringContainsString('session-1', $exception->getMessage());
  }
  // #endregion
}
