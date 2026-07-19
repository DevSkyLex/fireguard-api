<?php

declare(strict_types=1);

namespace Tests\Unit\Audit\Domain\Event;

use Audit\Domain\Event\AuditEventsExportedEvent;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test AuditEventsExportedEventTest.
 *
 * @category Event Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AuditEventsExportedEvent::class)]
final class AuditEventsExportedEventTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testConstructorCarriesOnlyFilterNamesNeverValues(): void
  {
    $event = new AuditEventsExportedEvent(
      actorUserId: 'user-1',
      tenantId: 'tenant-1',
      format: 'csv',
      rowCount: 42,
      filterKeys: ['action', 'from', 'to'],
    );

    self::assertSame('user-1', $event->actorUserId);
    self::assertSame('tenant-1', $event->tenantId);
    self::assertSame('csv', $event->format);
    self::assertSame(42, $event->rowCount);
    self::assertSame(['action', 'from', 'to'], $event->filterKeys);
    self::assertInstanceOf(DateTimeImmutable::class, $event->occurredAt);
  }
  // #endregion
}
