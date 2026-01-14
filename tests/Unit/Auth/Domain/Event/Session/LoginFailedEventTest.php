<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Domain\Event\Session;

use Auth\Domain\Event\Session\LoginFailedEvent;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test LoginFailedEventTest.
 *
 * @category Unit Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: LoginFailedEvent::class)]
final class LoginFailedEventTest extends TestCase
{
  // #region Methods
  /**
   * Method testCanBeCreated.
   */
  #[Test]
  public function testCanBeCreated(): void
  {
    $before = new DateTimeImmutable();
    $event = new LoginFailedEvent(
      email: 'user@example.com',
      ipAddress: '127.0.0.1',
      reason: 'invalid_credentials',
    );
    $after = new DateTimeImmutable();

    $this->assertSame('user@example.com', $event->email);
    $this->assertSame('127.0.0.1', $event->ipAddress);
    $this->assertSame('invalid_credentials', $event->reason);
    $this->assertGreaterThanOrEqual($before, $event->occurredAt);
    $this->assertLessThanOrEqual($after, $event->occurredAt);
  }
  // #endregion
}
