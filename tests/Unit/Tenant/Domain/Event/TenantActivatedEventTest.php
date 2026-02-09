<?php

declare(strict_types=1);

namespace Tests\Unit\Tenant\Domain\Event;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\ValueObject\Uuid;
use Tenant\Domain\Event\TenantActivatedEvent;

/**
 * Test TenantActivatedEventTest.
 *
 * @category Event Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(TenantActivatedEvent::class)]
final class TenantActivatedEventTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testPayload(): void
  {
    $event = new TenantActivatedEvent(
      eventId: new Uuid('00000000-0000-4000-a000-000000000010'),
      tenantId: 'tenant-1',
      tenantName: 'Acme',
      occurredAt: new DateTimeImmutable('2024-01-01T00:00:00+00:00'),
    );

    self::assertSame('tenant-1', $event->aggregateId());
    self::assertSame('Tenant', $event->aggregateType());

    $payload = $event->payload();
    self::assertSame('Acme', $payload['tenant_name']);
  }

  #[Test]
  public function testAccessorsReturnExpectedValues(): void
  {
    $eventId = new Uuid('00000000-0000-4000-a000-000000000011');
    $occurredAt = new DateTimeImmutable('2024-02-01T10:00:00+00:00');

    $event = new TenantActivatedEvent(
      eventId: $eventId,
      tenantId: 'tenant-2',
      tenantName: 'Beta',
      occurredAt: $occurredAt,
    );

    self::assertSame($eventId, $event->eventId());
    self::assertSame($occurredAt, $event->occurredAt());
    self::assertSame('tenant-2', $event->tenantId());
    self::assertSame('Beta', $event->tenantName());
  }
  // #endregion
}
