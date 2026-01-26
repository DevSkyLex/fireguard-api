<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Domain\Event\Token;

use DateTimeImmutable;
use OAuth\Domain\Event\Token\TokenRefreshFailedEvent;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Class TokenRefreshFailedEventTest.
 *
 * Unit tests for the TokenRefreshFailedEvent.
 *
 * @category Unit Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: TokenRefreshFailedEvent::class)]
final class TokenRefreshFailedEventTest extends TestCase
{
  // #region Methods
  /**
   * Method testCanBeCreated.
   */
  #[Test]
  public function testCanBeCreated(): void
  {
    $event = new TokenRefreshFailedEvent(
      userId: 'user-123',
      ipAddress: '127.0.0.1',
      reason: 'invalid_token',
    );

    $this->assertSame('user-123', $event->userId);
    $this->assertSame('127.0.0.1', $event->ipAddress);
    $this->assertSame('invalid_token', $event->reason);
    $this->assertInstanceOf(DateTimeImmutable::class, $event->occurredAt);
  }
  // #endregion
}
