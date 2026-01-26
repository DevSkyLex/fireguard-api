<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization\Domain\Event;

use Authorization\Domain\Event\RoleRevokedEvent;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\ValueObject\Uuid;

/**
 * Test RoleRevokedEventTest.
 *
 * @category Event Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(RoleRevokedEvent::class)]
final class RoleRevokedEventTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testPayloadAndMetadata(): void
  {
    $eventId = new Uuid('00000000-0000-4000-a000-000000000003');
    $occurredAt = new DateTimeImmutable('2024-01-01T00:00:00+00:00');

    $event = new RoleRevokedEvent(
      eventId: $eventId,
      assignmentId: 'assignment-1',
      roleId: 'role-1',
      subjectType: 'user',
      subjectId: 'user-1',
      occurredAt: $occurredAt,
    );

    self::assertSame($eventId, $event->eventId());
    self::assertSame($occurredAt, $event->occurredAt());
    self::assertSame('assignment-1', $event->aggregateId());
    self::assertSame('RoleAssignment', $event->aggregateType());

    $payload = $event->payload();
    self::assertSame('role-1', $payload['roleId']);
    self::assertSame('user-1', $payload['subjectId']);
  }
  // #endregion
}
