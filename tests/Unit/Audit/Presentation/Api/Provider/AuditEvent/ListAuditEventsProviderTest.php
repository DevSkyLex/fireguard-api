<?php

declare(strict_types=1);

namespace Tests\Unit\Audit\Presentation\Api\Provider\AuditEvent;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\Pagination\TraversablePaginator;
use Audit\Application\Contract\AuditEventView;
use Audit\Application\UseCase\Query\ListAuditEvents\ListAuditEventsQuery;
use Audit\Presentation\Api\Provider\AuditEvent\ListAuditEventsProvider;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Pagination\PaginatedResult;
use Shared\Application\Port\Inbound\QueryBusPort;

use function iterator_to_array;

/**
 * Test ListAuditEventsProviderTest.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListAuditEventsProvider::class)]
final class ListAuditEventsProviderTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testProvideReturnsPaginatedOutputs(): void
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

    $filters = [
      'page' => '2',
      'itemsPerPage' => '10',
      'action' => 'auth.login_success',
      'actorType' => 'user',
      'from' => '2026-01-30T00:00:00+00:00',
    ];

    $paginated = new PaginatedResult(items: [$view], total: 1, limit: 10, offset: 10);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(function (ListAuditEventsQuery $query): bool {
        return 'auth.login_success' === $query->criteria->action
          && 'user' === $query->criteria->actorType
          && $query->criteria->from instanceof DateTimeImmutable
          && 10 === $query->pagination->offset
          && 10 === $query->pagination->limit;
      }))
      ->willReturn($paginated);

    $provider = new ListAuditEventsProvider(queryBus: $queryBus);

    $result = $provider->provide(new GetCollection(), [], ['filters' => $filters]);

    self::assertInstanceOf(TraversablePaginator::class, $result);
    self::assertSame(2.0, $result->getCurrentPage());
    self::assertSame(10.0, $result->getItemsPerPage());
    self::assertSame(1.0, $result->getTotalItems());

    $items = iterator_to_array($result);
    self::assertCount(1, $items);
    self::assertSame('auth.login_success', $items[0]->action);
    self::assertSame('event-hash', $items[0]->eventHash);
  }

  #[Test]
  public function testProvideParsesNonAtomDates(): void
  {
    $filters = [
      'from' => '2026-01-30T00:00:00+00:00',
      'to' => '2026-01-30 10:00:00',
    ];

    $paginated = new PaginatedResult(items: [], total: 0, limit: 30, offset: 0);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(function (ListAuditEventsQuery $query): bool {
        $to = $query->criteria->to;

        return $to instanceof DateTimeImmutable
          && '2026-01-30 10:00:00' === $to->format('Y-m-d H:i:s');
      }))
      ->willReturn($paginated);

    $provider = new ListAuditEventsProvider(queryBus: $queryBus);

    $result = $provider->provide(new GetCollection(), [], ['filters' => $filters]);

    self::assertInstanceOf(TraversablePaginator::class, $result);
  }
  // #endregion
}
