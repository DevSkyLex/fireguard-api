<?php

declare(strict_types=1);

namespace Tests\Unit\Audit\Application\UseCase\Query;

use Audit\Application\Contract\AuditEventSearchCriteria;
use Audit\Application\UseCase\Query\GetAuditEvent\GetAuditEventQuery;
use Audit\Application\UseCase\Query\ListAuditEvents\ListAuditEventsQuery;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Pagination\Pagination;

/**
 * Test AuditEventQueryTest.
 *
 * @category Query Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetAuditEventQuery::class)]
#[CoversClass(ListAuditEventsQuery::class)]
final class AuditEventQueryTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testGetAuditEventQueryStoresId(): void
  {
    $query = new GetAuditEventQuery(eventId: 'evt-1');

    self::assertSame('evt-1', $query->eventId);
  }

  #[Test]
  public function testListAuditEventsQueryStoresCriteriaAndPagination(): void
  {
    $criteria = new AuditEventSearchCriteria(action: 'user.login');
    $pagination = new Pagination(limit: 20, offset: 10);

    $query = new ListAuditEventsQuery(criteria: $criteria, pagination: $pagination);

    self::assertSame($criteria, $query->criteria);
    self::assertSame($pagination, $query->pagination);
  }
  // #endregion
}
