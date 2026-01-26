<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Infrastructure\Adapter\Session;

use Auth\Infrastructure\Adapter\Session\SessionTrackingAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Session\Application\Port\Inbound\Tracking\SessionTrackingPort;

/**
 * Test SessionTrackingAdapterTest.
 *
 * @category Adapter Tests
 */
#[CoversClass(className: SessionTrackingAdapter::class)]
final class SessionTrackingAdapterTest extends TestCase
{
  // #region Tests
  #[Test]
  public function testRecordSessionDelegates(): void
  {
    $service = $this->createMock(SessionTrackingPort::class);
    $service->expects(self::once())
      ->method('recordSession')
      ->with('user-1', '127.0.0.1', 'agent', 'access-1', 'refresh-1', true);

    $adapter = new SessionTrackingAdapter($service);

    $adapter->recordSession('user-1', '127.0.0.1', 'agent', 'access-1', 'refresh-1', true);
  }

  #[Test]
  public function testRotateSessionTokensDelegates(): void
  {
    $service = $this->createMock(SessionTrackingPort::class);
    $service->expects(self::once())
      ->method('rotateSessionTokens')
      ->with('refresh-old', 'access-old', 'access-new', 'refresh-new');

    $adapter = new SessionTrackingAdapter($service);

    $adapter->rotateSessionTokens('refresh-old', 'access-old', 'access-new', 'refresh-new');
  }

  #[Test]
  public function testRevokeSessionByTokenDelegates(): void
  {
    $service = $this->createMock(SessionTrackingPort::class);
    $service->expects(self::once())
      ->method('revokeSessionByToken')
      ->with('refresh-1', 'access-1');

    $adapter = new SessionTrackingAdapter($service);

    $adapter->revokeSessionByToken('refresh-1', 'access-1');
  }
  // #endregion
}
