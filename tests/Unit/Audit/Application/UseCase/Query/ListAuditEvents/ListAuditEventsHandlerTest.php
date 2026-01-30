<?php

declare(strict_types=1);

namespace Tests\Unit\Audit\Application\UseCase\Query\ListAuditEvents;

use Audit\Application\Contract\AuditEventSearchCriteria;
use Audit\Application\Port\Outbound\AuditEventRepositoryPort;
use Audit\Application\UseCase\Query\ListAuditEvents\{ListAuditEventsHandler, ListAuditEventsQuery};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Pagination\{PaginatedResult, Pagination};

/**
 * Test ListAuditEventsHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListAuditEventsHandler::class)]
final class ListAuditEventsHandlerTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testInvokeReturnsPaginatedResults(): void
  {
    $criteria = new AuditEventSearchCriteria(action: 'auth.login_success');
    $pagination = new Pagination(offset: 10, limit: 5);
    $query = new ListAuditEventsQuery(criteria: $criteria, pagination: $pagination);

    $paginated = new PaginatedResult(items: [], total: 0, limit: 5, offset: 10);

    /** @var AuditEventRepositoryPort&MockObject $repository */
    $repository = $this->createMock(AuditEventRepositoryPort::class);
    $repository->expects(self::once())
      ->method('search')
      ->with($criteria, $pagination)
      ->willReturn($paginated);

    $handler = new ListAuditEventsHandler(repository: $repository);

    $result = $handler->__invoke($query);

    self::assertSame($paginated, $result);
  }
  // #endregion
}
