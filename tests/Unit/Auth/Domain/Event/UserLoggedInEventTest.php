<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Domain\Event;

use Auth\Domain\Event\UserLoggedInEvent;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

/**
 * Class UserLoggedInEventTest
 *
 * Unit tests for the UserLoggedInEvent.
 *
 * @category Unit Test
 * @package Tests\Unit\Auth\Domain\Event
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @covers \Auth\Domain\Event\UserLoggedInEvent
 */
#[CoversClass(className: UserLoggedInEvent::class)]
final class UserLoggedInEventTest extends TestCase
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
    $event = new UserLoggedInEvent(
      userId: 'user-123',
      email: 'test@example.com',
      ipAddress: '192.168.1.1',
    );

    $this->assertEquals(expected: 'user-123', actual: $event->userId);
    $this->assertEquals(expected: 'test@example.com', actual: $event->email);
    $this->assertEquals(expected: '192.168.1.1', actual: $event->ipAddress);
    $this->assertInstanceOf(expected: \DateTimeImmutable::class, actual: $event->occurredAt);
  }

  /**
   * Method testCanBeCreatedWithNullIpAddress
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testCanBeCreatedWithNullIpAddress(): void
  {
    $event = new UserLoggedInEvent(
      userId: 'user-123',
      email: 'test@example.com',
      ipAddress: null,
    );

    $this->assertNull(actual: $event->ipAddress);
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
    $event = new UserLoggedInEvent(
      userId: 'user-123',
      email: 'test@example.com',
    );
    $after = new \DateTimeImmutable();

    $this->assertGreaterThanOrEqual($before, $event->occurredAt);
    $this->assertLessThanOrEqual($after, $event->occurredAt);
  }
  //#endregion
}
