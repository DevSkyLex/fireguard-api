<?php

declare(strict_types=1);

namespace Tests\Unit\Audit\Domain\Model;

use Audit\Domain\Model\AuditEvent;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\ValueObject\Uuid;

/**
 * Test AuditEventTest.
 *
 * @category Domain Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AuditEvent::class)]
final class AuditEventTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testAuditEventStoresProperties(): void
  {
    $id = new Uuid('123e4567-e89b-12d3-a456-426614174000');
    $occurredAt = new DateTimeImmutable('2024-01-01T00:00:00+00:00');

    $event = new AuditEvent(
      id: $id,
      action: 'user.login',
      actorType: 'user',
      actorId: 'user-1',
      actorEmail: 'user@example.com',
      actorEmailHash: 'email-hash',
      subjectType: 'session',
      subjectId: 'session-1',
      clientId: 'client-1',
      tenantId: 'tenant-1',
      ipAddress: '127.0.0.1',
      ipHash: 'ip-hash',
      userAgent: 'agent',
      metadata: ['key' => 'value'],
      occurredAt: $occurredAt,
      recordedAt: null,
      chainId: 'global',
      sequence: 1,
      prevHash: null,
      eventHash: 'hash',
    );

    self::assertSame($id, $event->id);
    self::assertSame('user.login', $event->action);
    self::assertSame('user-1', $event->actorId);
    self::assertSame($occurredAt, $event->occurredAt);
    self::assertSame('hash', $event->eventHash);
  }
  // #endregion
}
