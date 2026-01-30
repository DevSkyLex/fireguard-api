<?php

declare(strict_types=1);

namespace Tests\Unit\Audit\Application\UseCase\Query\GetAuditEvent;

use Audit\Application\Contract\AuditEventView;
use Audit\Application\Port\Outbound\AuditEventRepositoryPort;
use Audit\Application\UseCase\Query\GetAuditEvent\{GetAuditEventHandler, GetAuditEventQuery};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\EntityNotFoundException;

/**
 * Test GetAuditEventHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetAuditEventHandler::class)]
final class GetAuditEventHandlerTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testInvokeReturnsAuditEventView(): void
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
      ipAddress: null,
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

    /** @var AuditEventRepositoryPort&MockObject $repository */
    $repository = $this->createMock(AuditEventRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findById')
      ->with('event-123')
      ->willReturn($view);

    $handler = new GetAuditEventHandler(repository: $repository);

    $result = $handler->__invoke(new GetAuditEventQuery(eventId: 'event-123'));

    self::assertSame($view, $result);
  }

  #[Test]
  public function testInvokeThrowsWhenNotFound(): void
  {
    /** @var AuditEventRepositoryPort&MockObject $repository */
    $repository = $this->createMock(AuditEventRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findById')
      ->with('missing-event')
      ->willReturn(null);

    $handler = new GetAuditEventHandler(repository: $repository);

    $this->expectException(EntityNotFoundException::class);

    $handler->__invoke(new GetAuditEventQuery(eventId: 'missing-event'));
  }
  // #endregion
}
