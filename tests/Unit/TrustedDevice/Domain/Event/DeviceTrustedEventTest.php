<?php

declare(strict_types=1);

namespace Tests\Unit\TrustedDevice\Domain\Event;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\ValueObject\Uuid;
use TrustedDevice\Domain\Event\DeviceTrustedEvent;

/**
 * Test DeviceTrustedEventTest.
 *
 * @category Unit Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: DeviceTrustedEvent::class)]
final class DeviceTrustedEventTest extends TestCase
{
  // #region Methods
  /**
   * Method testCanBeCreated.
   *
   * Tests that the event exposes metadata
   * and payload values.
   */
  #[Test]
  public function testCanBeCreated(): void
  {
    $before = new DateTimeImmutable();

    $event = new DeviceTrustedEvent(
      deviceId: 'device-123',
      userId: 'user-123',
      deviceName: 'Chrome on Windows',
    );

    $after = new DateTimeImmutable();

    self::assertInstanceOf(Uuid::class, $event->eventId());
    self::assertGreaterThanOrEqual($before, $event->occurredAt());
    self::assertLessThanOrEqual($after, $event->occurredAt());
    self::assertSame('device-123', $event->aggregateId());
    self::assertSame('TrustedDevice', $event->aggregateType());
    self::assertSame(
      [
        'deviceId' => 'device-123',
        'userId' => 'user-123',
        'deviceName' => 'Chrome on Windows',
      ],
      $event->payload(),
    );
  }
  // #endregion
}
