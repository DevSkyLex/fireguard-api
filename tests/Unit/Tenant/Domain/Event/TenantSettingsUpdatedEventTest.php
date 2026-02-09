<?php

declare(strict_types=1);

namespace Tests\Unit\Tenant\Domain\Event;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\ValueObject\Uuid;
use Tenant\Domain\Event\TenantSettingsUpdatedEvent;

/**
 * Test TenantSettingsUpdatedEventTest.
 *
 * @category Event Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(TenantSettingsUpdatedEvent::class)]
final class TenantSettingsUpdatedEventTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testPayload(): void
  {
    $event = new TenantSettingsUpdatedEvent(
      eventId: new Uuid('00000000-0000-4000-a000-000000000014'),
      tenantId: 'tenant-1',
      settings: ['access_token_ttl' => 7200],
      occurredAt: new DateTimeImmutable('2024-01-01T00:00:00+00:00'),
    );

    self::assertSame('tenant-1', $event->aggregateId());
    self::assertSame('Tenant', $event->aggregateType());

    $payload = $event->payload();
    self::assertSame(['access_token_ttl' => 7200], $payload['settings']);
  }

  #[Test]
  public function testAccessorsReturnExpectedValues(): void
  {
    $eventId = new Uuid('00000000-0000-4000-a000-000000000015');
    $occurredAt = new DateTimeImmutable('2024-02-01T10:00:00+00:00');
    $settings = ['refresh_token_ttl' => 86400];

    $event = new TenantSettingsUpdatedEvent(
      eventId: $eventId,
      tenantId: 'tenant-2',
      settings: $settings,
      occurredAt: $occurredAt,
    );

    self::assertSame($eventId, $event->eventId());
    self::assertSame($occurredAt, $event->occurredAt());
    self::assertSame('tenant-2', $event->tenantId());
    self::assertSame($settings, $event->settings());
  }
  // #endregion
}
