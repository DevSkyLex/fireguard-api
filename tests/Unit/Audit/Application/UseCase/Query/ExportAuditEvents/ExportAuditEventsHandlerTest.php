<?php

declare(strict_types=1);

namespace Tests\Unit\Audit\Application\UseCase\Query\ExportAuditEvents;

use Audit\Application\Contract\{AuditEventSearchCriteria, AuditEventView};
use Audit\Application\Contract\{AuditExportAccessDeniedException, AuditExportTooLargeException};
use Audit\Application\Contract\Event\AuditEventsExportedEvent;
use Audit\Application\Port\Outbound\Internal\AuditEventRepositoryPort;
use Audit\Application\UseCase\Query\ExportAuditEvents\{ExportAuditEventsHandler, ExportAuditEventsQuery, ExportAuditEventsResult};
use Authorization\Application\Port\Inbound\AuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\EventDispatcherPort;

use function iterator_to_array;

/**
 * Test ExportAuditEventsHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ExportAuditEventsHandler::class)]
final class ExportAuditEventsHandlerTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testInvokeReturnsStreamedResultUnderTheCap(): void
  {
    $criteria = new AuditEventSearchCriteria(action: 'auth.login_success');
    $query = new ExportAuditEventsQuery(criteria: $criteria, actorUserId: 'auditor-1');

    $view = new AuditEventView(
      id: '550e8400-e29b-41d4-a716-446655440000',
      action: 'auth.login_success',
      actorType: 'user',
      actorId: 'user-1',
      actorEmail: null,
      actorEmailHash: 'hash',
      subjectType: null,
      subjectId: null,
      clientId: null,
      tenantId: null,
      ipAddress: null,
      ipHash: null,
      userAgent: null,
      metadata: [],
      occurredAt: '2026-01-01T00:00:00+00:00',
      recordedAt: '2026-01-01T00:00:01+00:00',
      chainId: 'global',
      sequence: 1,
      prevHash: null,
      eventHash: 'event-hash',
    );

    $rows = (static function () use ($view) {
      yield $view;
    })();

    /** @var AuditEventRepositoryPort&MockObject $repository */
    $repository = $this->createMock(AuditEventRepositoryPort::class);
    $repository->expects(self::once())
      ->method('countMatching')
      ->with($criteria)
      ->willReturn(1);
    $repository->expects(self::once())
      ->method('stream')
      ->with($criteria)
      ->willReturn($rows);

    $handler = $this->handler($repository);

    $result = $handler->__invoke($query);

    self::assertInstanceOf(ExportAuditEventsResult::class, $result);
    self::assertSame(1, $result->total);
    self::assertSame([$view], iterator_to_array($result->rows));
  }

  #[Test]
  public function testInvokeThrowsWhenMatchCountExceedsTheCap(): void
  {
    $criteria = new AuditEventSearchCriteria();
    $query = new ExportAuditEventsQuery(criteria: $criteria, actorUserId: 'auditor-1');

    /** @var AuditEventRepositoryPort&MockObject $repository */
    $repository = $this->createMock(AuditEventRepositoryPort::class);
    $repository->expects(self::once())
      ->method('countMatching')
      ->with($criteria)
      ->willReturn(ExportAuditEventsHandler::MAX_EXPORT_ROWS + 1);
    $repository->expects(self::never())
      ->method('stream');

    $handler = $this->handler($repository);

    $this->expectException(AuditExportTooLargeException::class);

    $handler->__invoke($query);
  }

  #[Test]
  public function testInvokeAllowsExactlyTheCap(): void
  {
    $criteria = new AuditEventSearchCriteria();
    $query = new ExportAuditEventsQuery(criteria: $criteria, actorUserId: 'auditor-1');

    /** @var AuditEventRepositoryPort&MockObject $repository */
    $repository = $this->createMock(AuditEventRepositoryPort::class);
    $repository->expects(self::once())
      ->method('countMatching')
      ->with($criteria)
      ->willReturn(ExportAuditEventsHandler::MAX_EXPORT_ROWS);
    $repository->expects(self::once())
      ->method('stream')
      ->with($criteria)
      ->willReturn([]);

    $handler = $this->handler($repository);

    $result = $handler->__invoke($query);

    self::assertSame(ExportAuditEventsHandler::MAX_EXPORT_ROWS, $result->total);
  }

  #[Test]
  public function testItAuditsApprovedExportsWithFilterNamesOnly(): void
  {
    $criteria = new AuditEventSearchCriteria(actorId: 'private-filter-value', tenantId: 'tenant');
    $repository = $this->createStub(AuditEventRepositoryPort::class);
    $repository->method('countMatching')->willReturn(42);
    $repository->method('stream')->willReturn([]);
    $authorization = $this->createMock(AuthorizationPort::class);
    $authorization->expects(self::once())->method('hasPermission')->with('auditor', 'audit.export')->willReturn(true);
    $events = $this->createMock(EventDispatcherPort::class);
    $events->expects(self::once())->method('dispatch')->with(self::callback(static fn (AuditEventsExportedEvent $event): bool => 'auditor' === $event->actorUserId
      && 'tenant' === $event->tenantId
      && 'csv' === $event->format
      && 42 === $event->rowCount
      && ['actorId', 'tenantId'] === $event->filterKeys));

    $result = new ExportAuditEventsHandler($repository, $authorization, $events)(new ExportAuditEventsQuery($criteria, 'auditor'));
    self::assertSame(42, $result->total);
  }

  #[Test]
  public function testItDeniesBeforeReadingOrAuditingTheLedger(): void
  {
    $repository = $this->createMock(AuditEventRepositoryPort::class);
    $repository->expects(self::never())->method('countMatching');
    $repository->expects(self::never())->method('stream');
    $authorization = $this->createMock(AuthorizationPort::class);
    $authorization->expects(self::once())->method('hasPermission')->with('ordinary-user', 'audit.export')->willReturn(false);
    $events = $this->createMock(EventDispatcherPort::class);
    $events->expects(self::never())->method('dispatch');
    $this->expectException(AuditExportAccessDeniedException::class);
    $this->expectExceptionMessage('Insufficient permissions (requires audit.export).');
    new ExportAuditEventsHandler($repository, $authorization, $events)(new ExportAuditEventsQuery(new AuditEventSearchCriteria(), 'ordinary-user'));
  }

  private function handler(AuditEventRepositoryPort $repository): ExportAuditEventsHandler
  {
    $authorization = $this->createStub(AuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    return new ExportAuditEventsHandler($repository, $authorization, $this->createStub(EventDispatcherPort::class));
  }
  // #endregion
}
