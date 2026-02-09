<?php

declare(strict_types=1);

namespace Tests\Unit\Audit\Application\Contract;

use Audit\Application\Contract\{AuditEventSearchCriteria, AuditEventView};
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test AuditEventContractTest.
 *
 * @category Contract Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AuditEventSearchCriteria::class)]
#[CoversClass(AuditEventView::class)]
final class AuditEventContractTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testSearchCriteriaStoresFilters(): void
  {
    $from = new DateTimeImmutable('2024-01-01T00:00:00+00:00');
    $to = new DateTimeImmutable('2024-01-02T00:00:00+00:00');

    $criteria = new AuditEventSearchCriteria(
      action: 'user.login',
      actorType: 'user',
      actorId: 'user-1',
      subjectType: 'session',
      subjectId: 'session-1',
      clientId: 'client-1',
      tenantId: 'tenant-1',
      ipHash: 'ip-hash',
      actorEmailHash: 'email-hash',
      from: $from,
      to: $to,
    );

    self::assertSame('user.login', $criteria->action);
    self::assertSame('user', $criteria->actorType);
    self::assertSame('user-1', $criteria->actorId);
    self::assertSame('session', $criteria->subjectType);
    self::assertSame('session-1', $criteria->subjectId);
    self::assertSame('client-1', $criteria->clientId);
    self::assertSame('tenant-1', $criteria->tenantId);
    self::assertSame('ip-hash', $criteria->ipHash);
    self::assertSame('email-hash', $criteria->actorEmailHash);
    self::assertSame($from, $criteria->from);
    self::assertSame($to, $criteria->to);
  }

  #[Test]
  public function testAuditEventViewStoresPayload(): void
  {
    $view = new AuditEventView(
      id: 'evt-1',
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
      occurredAt: '2024-01-01T00:00:00+00:00',
      recordedAt: '2024-01-01T00:00:01+00:00',
      chainId: 'global',
      sequence: 1,
      prevHash: null,
      eventHash: 'hash',
    );

    self::assertSame('evt-1', $view->id);
    self::assertSame('user.login', $view->action);
    self::assertSame('user', $view->actorType);
    self::assertSame('user-1', $view->actorId);
    self::assertSame('user@example.com', $view->actorEmail);
    self::assertSame(['key' => 'value'], $view->metadata);
    self::assertSame('global', $view->chainId);
  }
  // #endregion
}
