<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Domain\Event\Session;

use Auth\Domain\Event\Session\UserLoggedOutEvent;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Class UserLoggedOutEventTest.
 *
 * Unit tests for the UserLoggedOutEvent.
 *
 * @category Unit Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @covers \Auth\Domain\Event\Session\UserLoggedOutEvent
 */
#[CoversClass(className: UserLoggedOutEvent::class)]
final class UserLoggedOutEventTest extends TestCase
{
  // #region Methods
  /**
   * Method testCanBeCreated.
   */
  #[Test]
  public function testCanBeCreated(): void
  {
    $event = new UserLoggedOutEvent(
      userId: 'user-123',
      ipAddress: '192.168.1.1',
      refreshTokenRevoked: true,
      accessTokenRevoked: true,
    );

    $this->assertEquals(expected: 'user-123', actual: $event->userId);
    $this->assertEquals(expected: '192.168.1.1', actual: $event->ipAddress);
    $this->assertTrue(condition: $event->refreshTokenRevoked);
    $this->assertTrue(condition: $event->accessTokenRevoked);
    $this->assertInstanceOf(expected: DateTimeImmutable::class, actual: $event->occurredAt);
  }

  /**
   * Method testCanBeCreatedWithNullUserId.
   */
  #[Test]
  public function testCanBeCreatedWithNullUserId(): void
  {
    $event = new UserLoggedOutEvent(
      userId: null,
    );

    $this->assertNull(actual: $event->userId);
    $this->assertFalse(condition: $event->refreshTokenRevoked);
    $this->assertFalse(condition: $event->accessTokenRevoked);
  }

  /**
   * Method testOccurredAtIsSetAutomatically.
   */
  #[Test]
  public function testOccurredAtIsSetAutomatically(): void
  {
    $before = new DateTimeImmutable();
    $event = new UserLoggedOutEvent(
      userId: 'user-123',
    );
    $after = new DateTimeImmutable();

    $this->assertGreaterThanOrEqual($before, $event->occurredAt);
    $this->assertLessThanOrEqual($after, $event->occurredAt);
  }
  // #endregion
}
