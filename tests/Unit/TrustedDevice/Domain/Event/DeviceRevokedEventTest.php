<?php

declare(strict_types=1);

namespace Tests\Unit\TrustedDevice\Domain\Event;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\ValueObject\Uuid;
use TrustedDevice\Domain\Event\DeviceRevokedEvent;

/**
 * Test DeviceRevokedEventTest.
 *
 * @category Unit Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: DeviceRevokedEvent::class)]
final class DeviceRevokedEventTest extends TestCase
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

    $event = new DeviceRevokedEvent(
      deviceId: 'device-456',
      userId: 'user-456',
      deviceName: 'Firefox on Linux',
    );

    $after = new DateTimeImmutable();

    self::assertInstanceOf(Uuid::class, $event->eventId());
    self::assertGreaterThanOrEqual($before, $event->occurredAt());
    self::assertLessThanOrEqual($after, $event->occurredAt());
    self::assertSame('device-456', $event->aggregateId());
    self::assertSame('TrustedDevice', $event->aggregateType());
    self::assertSame(
      [
        'deviceId' => 'device-456',
        'userId' => 'user-456',
        'deviceName' => 'Firefox on Linux',
      ],
      $event->payload(),
    );
  }
  // #endregion
}
