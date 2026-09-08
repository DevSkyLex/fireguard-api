<?php

declare(strict_types=1);

namespace Tests\Unit\Maintenance\Presentation\Api\Controller;

use Auth\Infrastructure\Security\User\SecurityUser;
use Maintenance\Application\Contract\Export\MaintenanceScheduleExportRow;
use Maintenance\Application\UseCase\Query\ExportMaintenanceSchedules\ExportMaintenanceSchedulesResult;
use Maintenance\Domain\Event\Export\MaintenanceSchedulesExportedEvent;
use Maintenance\Domain\Exception\{MaintenanceAccessDeniedException, MaintenanceExportTooLargeException, MaintenanceNotFoundException};
use Maintenance\Presentation\Api\Controller\ExportMaintenanceSchedulesController;
use Maintenance\Presentation\Api\Service\{MaintenanceScheduleCsvWriter, MaintenanceScheduleExportCriteriaFactory};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Application\Port\Outbound\EventDispatcherPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, StreamedResponse};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException, UnprocessableEntityHttpException};

use function ob_get_clean;
use function ob_start;

/**
 * Test ExportMaintenanceSchedulesControllerTest.
 *
 * Mirrors `Intervention\...\ExportInterventionsControllerTest` — the CSV
 * body itself is asserted here, at the controller unit-test level, since
 * `StreamedResponse::getContent()` is not reliably buffered by the
 * functional `KernelBrowser` test client.
 *
 * @category Controller Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ExportMaintenanceSchedulesController::class)]
final class ExportMaintenanceSchedulesControllerTest extends TestCase
{
  private const string USER_ID = '550e8400-e29b-41d4-a716-446655507101';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655507102';

  #[Test]
  public function testItStreamsTheMatchingSchedulesAsCsv(): void
  {
    $request = new Request(['organization' => '/api/organizations/' . self::ORGANIZATION_ID]);

    $row = new MaintenanceScheduleExportRow(
      id: 'schedule-1',
      equipmentId: 'equipment-1',
      equipmentType: 'extinguisher',
      equipmentSerial: 'SN-001',
      facilityId: 'facility-1',
      facilityName: 'Main Warehouse',
      intervalOverride: 'P6M',
      lastInspectionClosedAt: '2026-07-01T00:00:00+00:00',
      nextDueAt: '2027-01-01T00:00:00+00:00',
      dueStatus: 'up_to_date',
      createdAt: '2026-01-01T00:00:00+00:00',
      updatedAt: '2026-07-01T00:00:00+00:00',
    );

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturn(new ExportMaintenanceSchedulesResult(rows: [$row], total: 1));

    $response = $this->createController($queryBus, $this->createStub(EventDispatcherPort::class))->__invoke($request);

    self::assertSame('text/csv; charset=utf-8', $response->headers->get('Content-Type'));
    self::assertSame('no', $response->headers->get('X-Accel-Buffering'));

    $disposition = (string) $response->headers->get('Content-Disposition');
    self::assertStringStartsWith('attachment; filename="maintenance-schedules-export-', $disposition);
    self::assertStringEndsWith('.csv"', $disposition);

    ob_start();
    $response->sendContent();
    $csv = (string) ob_get_clean();

    self::assertStringContainsString('id,equipment_id,equipment_type,equipment_serial,facility,periodicity_override,last_inspection_closed_at,next_due_at,due_status,created_at,updated_at', $csv);
    self::assertStringContainsString('SN-001', $csv);
    self::assertStringContainsString('Main Warehouse', $csv);
  }

  #[Test]
  public function testItRejectsARequestWithoutTheOrganizationFilter(): void
  {
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('The organization filter is required.');

    $this->createController($queryBus, $this->createStub(EventDispatcherPort::class))->__invoke(new Request());
  }

  #[Test]
  public function testItAuditsTheExportWithFilterNamesOnly(): void
  {
    $request = new Request([
      'organization' => '/api/organizations/' . self::ORGANIZATION_ID,
      'dueStatus' => 'overdue',
    ]);

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturn(new ExportMaintenanceSchedulesResult(rows: [], total: 7));

    $dispatched = null;

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->willReturnCallback(static function (object $event) use (&$dispatched): void {
        $dispatched = $event;
      });

    $this->createController($queryBus, $eventDispatcher)->__invoke($request);

    self::assertInstanceOf(MaintenanceSchedulesExportedEvent::class, $dispatched);
    self::assertSame(self::USER_ID, $dispatched->actorUserId);
    self::assertSame(self::ORGANIZATION_ID, $dispatched->organizationId);
    self::assertSame('csv', $dispatched->format);
    self::assertSame(7, $dispatched->rowCount);
    self::assertSame(['dueStatus'], $dispatched->filterKeys);
  }

  #[Test]
  public function testItRefusesAnUnauthenticatedCaller(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $controller = new ExportMaintenanceSchedulesController(
      queryBus: $queryBus,
      eventDispatcher: $this->createStub(EventDispatcherPort::class),
      security: $security,
      criteriaFactory: new MaintenanceScheduleExportCriteriaFactory(),
      csvWriter: new MaintenanceScheduleCsvWriter(),
    );

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Authentication required.');

    $controller->__invoke(new Request());
  }

  #[Test]
  public function testItMapsAccessDeniedToForbidden(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(new MaintenanceAccessDeniedException('Missing organization.maintenance.read permission.'));

    $this->expectException(AccessDeniedHttpException::class);

    $this->createController($queryBus, $this->createStub(EventDispatcherPort::class))
      ->__invoke(new Request(['organization' => '/api/organizations/' . self::ORGANIZATION_ID]));
  }

  #[Test]
  public function testItMapsOutOfScopeOrganizationToNotFound(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(MaintenanceNotFoundException::forOrganizationScope(self::ORGANIZATION_ID));

    $this->expectException(NotFoundHttpException::class);

    $this->createController($queryBus, $this->createStub(EventDispatcherPort::class))
      ->__invoke(new Request(['organization' => '/api/organizations/' . self::ORGANIZATION_ID]));
  }

  #[Test]
  public function testItRefusesAnExportExceedingTheRowCapThroughTheBusWrapping(): void
  {
    // The real query bus never surfaces the domain exception directly: Messenger
    // wraps it in HandlerFailedException, re-wrapped by the adapter. The mapper
    // must unwrap that chain, or the documented 422 degrades to a 500.
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(new RuntimeException(
      'Query handling failed.',
      0,
      new RuntimeException(
        'Handler failed.',
        0,
        MaintenanceExportTooLargeException::exceedsCap(60_000, 50_000),
      ),
    ));

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $this->expectException(UnprocessableEntityHttpException::class);

    $this->createController($queryBus, $eventDispatcher)
      ->__invoke(new Request(['organization' => '/api/organizations/' . self::ORGANIZATION_ID]));
  }

  private function createController(QueryBusPort $queryBus, EventDispatcherPort $eventDispatcher): ExportMaintenanceSchedulesController
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(new SecurityUser(
      id: self::USER_ID,
      email: 'exporter@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    ));

    return new ExportMaintenanceSchedulesController(
      queryBus: $queryBus,
      eventDispatcher: $eventDispatcher,
      security: $security,
      criteriaFactory: new MaintenanceScheduleExportCriteriaFactory(),
      csvWriter: new MaintenanceScheduleCsvWriter(),
    );
  }
}
