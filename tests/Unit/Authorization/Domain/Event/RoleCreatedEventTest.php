<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization\Domain\Event;

use Authorization\Domain\Event\RoleCreatedEvent;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\ValueObject\Uuid;

/**
 * Test RoleCreatedEventTest.
 *
 * @category Event Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(RoleCreatedEvent::class)]
final class RoleCreatedEventTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testPayloadAndMetadata(): void
  {
    $eventId = new Uuid('00000000-0000-4000-a000-000000000002');
    $occurredAt = new DateTimeImmutable('2024-01-01T00:00:00+00:00');

    $event = new RoleCreatedEvent(
      eventId: $eventId,
      roleId: 'role-1',
      roleName: 'Admin',
      isSystem: true,
      tenantId: 'tenant-1',
      occurredAt: $occurredAt,
    );

    self::assertSame($eventId, $event->eventId());
    self::assertSame($occurredAt, $event->occurredAt());
    self::assertSame('role-1', $event->aggregateId());
    self::assertSame('Role', $event->aggregateType());

    $payload = $event->payload();
    self::assertSame('Admin', $payload['roleName']);
    self::assertTrue($payload['isSystem']);
    self::assertSame('tenant-1', $payload['tenantId']);
  }
  // #endregion
}
