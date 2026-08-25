<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Infrastructure\Adapter\Session;

use Auth\Infrastructure\Adapter\Session\SessionStatusAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Session\Application\Port\Inbound\Tracking\SessionStatusPort;

/**
 * Test SessionStatusAdapterTest.
 *
 * @category Adapter Tests
 */
#[CoversClass(className: SessionStatusAdapter::class)]
final class SessionStatusAdapterTest extends TestCase
{
  // #region Tests
  #[Test]
  public function testIsAccessTokenRevokedDelegatesAndReturnsTrue(): void
  {
    $service = $this->createMock(SessionStatusPort::class);
    $service->expects(self::once())
      ->method('isAccessTokenRevoked')
      ->with('access-1')
      ->willReturn(true);

    self::assertTrue(new SessionStatusAdapter($service)->isAccessTokenRevoked('access-1'));
  }

  #[Test]
  public function testIsAccessTokenRevokedDelegatesAndReturnsFalse(): void
  {
    $service = $this->createMock(SessionStatusPort::class);
    $service->expects(self::once())
      ->method('isAccessTokenRevoked')
      ->with('access-2')
      ->willReturn(false);

    self::assertFalse(new SessionStatusAdapter($service)->isAccessTokenRevoked('access-2'));
  }
  // #endregion
}
