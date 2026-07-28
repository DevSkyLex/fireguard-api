<?php

declare(strict_types=1);

namespace Tests\Unit\Audit\Presentation\Api\Controller;

use Audit\Application\Contract\AuditEventView;
use Audit\Application\UseCase\Query\ExportAuditEvents\{ExportAuditEventsQuery, ExportAuditEventsResult};
use Audit\Domain\Event\AuditEventsExportedEvent;
use Audit\Domain\Exception\AuditExportTooLargeException;
use Audit\Presentation\Api\Controller\ExportAuditEventsController;
use Audit\Presentation\Api\Service\{AuditEventCsvWriter, AuditEventExportCriteriaFactory};
use Auth\Infrastructure\Security\User\SecurityUser;
use PHPUnit\Framework\Attributes\{CoversClass, IgnoreDeprecations, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Application\Port\Outbound\EventDispatcherPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, StreamedResponse};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, UnprocessableEntityHttpException};

use function json_encode;
use function ob_get_clean;
use function ob_start;
use function str_contains;

/**
 * Test ExportAuditEventsControllerTest.
 *
 * The audit ledger is the tamper-evident record of who did what, so its
 * export must stay authenticated, must refuse an over-cap export rather than
 * stream it, and must itself be audited — with filter NAMES only, never the
 * person-identifying filter values.
 *
 * @category Controller Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ExportAuditEventsController::class)]
#[IgnoreDeprecations]
final class ExportAuditEventsControllerTest extends TestCase
{
  private const string USER_ID = '550e8400-e29b-41d4-a716-446655506001';

  private const string TENANT_ID = '550e8400-e29b-41d4-a716-446655506002';

  #[Test]
  public function testItStreamsTheMatchingEventsAsCsv(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturn(new ExportAuditEventsResult(rows: [$this->view()], total: 1));

    $response = $this->createController($queryBus, $this->createStub(EventDispatcherPort::class))
      ->__invoke(new Request());

    self::assertInstanceOf(StreamedResponse::class, $response);
    self::assertSame('text/csv; charset=utf-8', $response->headers->get('Content-Type'));
    self::assertSame('no', $response->headers->get('X-Accel-Buffering'));

    $disposition = (string) $response->headers->get('Content-Disposition');
    self::assertStringStartsWith('attachment; filename="audit-events-export-', $disposition);
    self::assertStringEndsWith('.csv"', $disposition);

    ob_start();
    $response->sendContent();
    $csv = (string) ob_get_clean();

    self::assertStringContainsString('id,action,actor_type', $csv);
    self::assertStringContainsString('user.login', $csv);
  }

  #[Test]
  public function testItPassesTheRequestFiltersToTheQuery(): void
  {
    $request = new Request();
    $request->query->set('action', 'user.login');
    $request->query->set('tenantId', self::TENANT_ID);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static fn (ExportAuditEventsQuery $query): bool => 'user.login' === $query->criteria->action
        && self::TENANT_ID === $query->criteria->tenantId
        && null === $query->criteria->actorId))
      ->willReturn(new ExportAuditEventsResult(rows: [], total: 0));

    $this->createController($queryBus, $this->createStub(EventDispatcherPort::class))->__invoke($request);
  }

  #[Test]
  public function testItAuditsTheExportWithFilterNamesOnly(): void
  {
    $request = new Request();
    $request->query->set('actorId', 'a-very-identifying-user-id');
    $request->query->set('tenantId', self::TENANT_ID);

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturn(new ExportAuditEventsResult(rows: [], total: 42));

    $dispatched = null;

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->willReturnCallback(static function (object $event) use (&$dispatched): void {
        $dispatched = $event;
      });

    $this->createController($queryBus, $eventDispatcher)->__invoke($request);

    self::assertInstanceOf(AuditEventsExportedEvent::class, $dispatched);
    self::assertSame(self::USER_ID, $dispatched->actorUserId);
    self::assertSame(self::TENANT_ID, $dispatched->tenantId);
    self::assertSame('csv', $dispatched->format);
    self::assertSame(42, $dispatched->rowCount);
    self::assertSame(['actorId', 'tenantId'], $dispatched->filterKeys);
    self::assertFalse(
      str_contains((string) json_encode($dispatched->filterKeys), 'a-very-identifying-user-id'),
      'The export audit trail must never carry raw filter values.',
    );
  }

  #[Test]
  public function testItRefusesAnUnauthenticatedCaller(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $controller = new ExportAuditEventsController(
      queryBus: $queryBus,
      eventDispatcher: $this->createStub(EventDispatcherPort::class),
      security: $security,
      criteriaFactory: new AuditEventExportCriteriaFactory(),
      csvWriter: new AuditEventCsvWriter(),
    );

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Authentication required.');

    $controller->__invoke(new Request());
  }

  #[Test]
  public function testItRefusesAnExportExceedingTheRowCap(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(AuditExportTooLargeException::exceedsCap(120000, 100000));

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $this->expectException(UnprocessableEntityHttpException::class);

    $this->createController($queryBus, $eventDispatcher)->__invoke(new Request());
  }

  private function createController(QueryBusPort $queryBus, EventDispatcherPort $eventDispatcher): ExportAuditEventsController
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(new SecurityUser(
      id: self::USER_ID,
      email: 'auditor@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    ));

    return new ExportAuditEventsController(
      queryBus: $queryBus,
      eventDispatcher: $eventDispatcher,
      security: $security,
      criteriaFactory: new AuditEventExportCriteriaFactory(),
      csvWriter: new AuditEventCsvWriter(),
    );
  }

  private function view(): AuditEventView
  {
    return new AuditEventView(
      id: '550e8400-e29b-41d4-a716-446655506003',
      action: 'user.login',
      actorType: 'user',
      actorId: self::USER_ID,
      actorEmail: null,
      actorEmailHash: 'hash',
      subjectType: 'user',
      subjectId: self::USER_ID,
      clientId: null,
      tenantId: self::TENANT_ID,
      ipAddress: null,
      ipHash: 'ip-hash',
      userAgent: null,
      metadata: ['mfa' => true],
      occurredAt: '2026-06-01T08:00:00+00:00',
      recordedAt: '2026-06-01T08:00:01+00:00',
      chainId: 'chain-1',
      sequence: 7,
      prevHash: null,
      eventHash: 'event-hash',
    );
  }
}
