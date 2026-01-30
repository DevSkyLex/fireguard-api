<?php

declare(strict_types=1);

namespace Tests\Unit\Audit\Presentation\Api\Provider\AuditEvent;

use ApiPlatform\Metadata\Get;
use Audit\Application\Contract\AuditEventView;
use Audit\Application\UseCase\Query\GetAuditEvent\GetAuditEventQuery;
use Audit\Presentation\Api\Dto\Output\AuditEvent\AuditEventOutput;
use Audit\Presentation\Api\Provider\AuditEvent\GetAuditEventProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;

/**
 * Test GetAuditEventProviderTest.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetAuditEventProvider::class)]
final class GetAuditEventProviderTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testProvideMapsAuditEventView(): void
  {
    $view = new AuditEventView(
      id: 'event-123',
      action: 'auth.login_success',
      actorType: 'user',
      actorId: 'user-123',
      actorEmail: 'user@example.com',
      actorEmailHash: 'email-hash',
      subjectType: 'token',
      subjectId: 'token-123',
      clientId: 'client-123',
      tenantId: 'tenant-123',
      ipAddress: '203.0.113.10',
      ipHash: 'ip-hash',
      userAgent: 'Mozilla',
      metadata: ['reason' => 'success'],
      occurredAt: '2026-01-30T09:45:00+00:00',
      recordedAt: '2026-01-30T09:45:01+00:00',
      chainId: 'global',
      sequence: 1,
      prevHash: null,
      eventHash: 'event-hash',
    );

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(fn (GetAuditEventQuery $query): bool => 'event-123' === $query->eventId))
      ->willReturn($view);

    $provider = new GetAuditEventProvider(queryBus: $queryBus);

    $output = $provider->provide(new Get(), ['id' => 'event-123']);

    self::assertInstanceOf(AuditEventOutput::class, $output);
    self::assertSame('event-123', $output->id);
    self::assertSame('auth.login_success', $output->action);
    self::assertSame('user-123', $output->actorId);
    self::assertSame('event-hash', $output->eventHash);
  }
  // #endregion
}
