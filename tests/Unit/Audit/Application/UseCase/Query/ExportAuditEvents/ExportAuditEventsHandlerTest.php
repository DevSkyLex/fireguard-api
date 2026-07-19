<?php

declare(strict_types=1);

namespace Tests\Unit\Audit\Application\UseCase\Query\ExportAuditEvents;

use Audit\Application\Contract\{AuditEventSearchCriteria, AuditEventView};
use Audit\Application\Port\Outbound\AuditEventRepositoryPort;
use Audit\Application\UseCase\Query\ExportAuditEvents\{ExportAuditEventsHandler, ExportAuditEventsQuery, ExportAuditEventsResult};
use Audit\Domain\Exception\AuditExportTooLargeException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

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
    $query = new ExportAuditEventsQuery(criteria: $criteria);

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

    $handler = new ExportAuditEventsHandler(repository: $repository);

    $result = $handler->__invoke($query);

    self::assertInstanceOf(ExportAuditEventsResult::class, $result);
    self::assertSame(1, $result->total);
    self::assertSame([$view], iterator_to_array($result->rows));
  }

  #[Test]
  public function testInvokeThrowsWhenMatchCountExceedsTheCap(): void
  {
    $criteria = new AuditEventSearchCriteria();
    $query = new ExportAuditEventsQuery(criteria: $criteria);

    /** @var AuditEventRepositoryPort&MockObject $repository */
    $repository = $this->createMock(AuditEventRepositoryPort::class);
    $repository->expects(self::once())
      ->method('countMatching')
      ->with($criteria)
      ->willReturn(ExportAuditEventsHandler::MAX_EXPORT_ROWS + 1);
    $repository->expects(self::never())
      ->method('stream');

    $handler = new ExportAuditEventsHandler(repository: $repository);

    $this->expectException(AuditExportTooLargeException::class);

    $handler->__invoke($query);
  }

  #[Test]
  public function testInvokeAllowsExactlyTheCap(): void
  {
    $criteria = new AuditEventSearchCriteria();
    $query = new ExportAuditEventsQuery(criteria: $criteria);

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

    $handler = new ExportAuditEventsHandler(repository: $repository);

    $result = $handler->__invoke($query);

    self::assertSame(ExportAuditEventsHandler::MAX_EXPORT_ROWS, $result->total);
  }
  // #endregion
}
