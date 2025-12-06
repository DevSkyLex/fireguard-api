<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Domain\Event;

use Auth\Domain\Event\UserLoggedOutEvent;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

/**
 * Class UserLoggedOutEventTest
 *
 * Unit tests for the UserLoggedOutEvent.
 *
 * @category Unit Test
 * @package Tests\Unit\Auth\Domain\Event
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @covers \Auth\Domain\Event\UserLoggedOutEvent
 */
#[CoversClass(className: UserLoggedOutEvent::class)]
final class UserLoggedOutEventTest extends TestCase
{
  //#region Methods
  /**
   * Method testCanBeCreated
   *
   * @access public
   *
   * @return void
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
    $this->assertInstanceOf(expected: \DateTimeImmutable::class, actual: $event->occurredAt);
  }

  /**
   * Method testCanBeCreatedWithNullUserId
   *
   * @access public
   *
   * @return void
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
   * Method testOccurredAtIsSetAutomatically
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testOccurredAtIsSetAutomatically(): void
  {
    $before = new \DateTimeImmutable();
    $event = new UserLoggedOutEvent(
      userId: 'user-123',
    );
    $after = new \DateTimeImmutable();

    $this->assertGreaterThanOrEqual($before, $event->occurredAt);
    $this->assertLessThanOrEqual($after, $event->occurredAt);
  }
  //#endregion
}
