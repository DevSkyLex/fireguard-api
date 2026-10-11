<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Presentation\Api\Controller;

use Auth\Infrastructure\Security\User\SecurityUser;
use Equipment\Application\Contract\Export\EquipmentExportRow;
use Equipment\Application\UseCase\Query\ExportEquipments\{ExportEquipmentsQuery, ExportEquipmentsResult};
use Equipment\Domain\Exception\{EquipmentAccessDeniedException, EquipmentExportTooLargeException, EquipmentNotFoundException};
use Equipment\Presentation\Api\Controller\ExportEquipmentsController;
use Equipment\Presentation\Api\Service\EquipmentCsvWriter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Application\Port\Outbound\EventDispatcherPort;
use Shared\Infrastructure\Csv\SpreadsheetSafeTextAdapter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, StreamedResponse};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException, UnprocessableEntityHttpException};

use function ob_get_clean;
use function ob_start;

/**
 * Test ExportEquipmentsControllerTest.
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
#[CoversClass(ExportEquipmentsController::class)]
final class ExportEquipmentsControllerTest extends TestCase
{
  private const string USER_ID = '550e8400-e29b-41d4-a716-446655560001';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655560002';

  #[Test]
  public function testItStreamsTheMatchingEquipmentAsCsv(): void
  {
    $request = new Request(attributes: ['organizationId' => self::ORGANIZATION_ID]);

    $row = new EquipmentExportRow(
      id: 'equipment-1',
      type: 'fire_extinguisher',
      subType: 'CO2',
      brand: 'Acme',
      model: 'X100',
      serialNumber: 'SN-1',
      locationLabel: 'Hallway',
      status: 'operational',
      facilityId: 'facility-1',
      facilityCode: 'WH-01',
      facilityName: 'Main Warehouse',
      installedAt: '2026-01-01T00:00:00+00:00',
      commissionedAt: '2026-01-02T00:00:00+00:00',
      createdAt: '2026-01-01T00:00:00+00:00',
      updatedAt: '2026-01-02T00:00:00+00:00',
    );

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturn(new ExportEquipmentsResult(rows: [$row], total: 1));

    $response = $this->createController($queryBus, $this->createStub(EventDispatcherPort::class))->__invoke($request);

    self::assertInstanceOf(StreamedResponse::class, $response);
    self::assertSame('text/csv; charset=utf-8', $response->headers->get('Content-Type'));
    self::assertSame('no', $response->headers->get('X-Accel-Buffering'));

    $disposition = (string) $response->headers->get('Content-Disposition');
    self::assertStringStartsWith('attachment; filename="equipments-export-', $disposition);
    self::assertStringEndsWith('.csv"', $disposition);

    ob_start();
    $response->sendContent();
    $csv = (string) ob_get_clean();

    self::assertStringContainsString('type,subType,brand,model,serialNumber,locationLabel,facilityCode', $csv);
    self::assertStringContainsString('fire_extinguisher', $csv);
    self::assertStringContainsString('Main Warehouse', $csv);
  }

  #[Test]
  public function testItRejectsARequestWithoutTheOrganizationIdUriVariable(): void
  {
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('OrganizationId URI parameter is required.');

    $this->createController($queryBus, $this->createStub(EventDispatcherPort::class))->__invoke(new Request());
  }

  #[Test]
  public function testItDelegatesExportAuditingToTheOwnerUseCase(): void
  {
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())->method('ask')->with(self::callback(static fn (object $query): bool => $query instanceof ExportEquipmentsQuery && self::USER_ID === $query->userId && self::ORGANIZATION_ID === $query->organizationId))->willReturn(new ExportEquipmentsResult([], 0));
    $events = $this->createMock(EventDispatcherPort::class);
    $events->expects(self::never())->method('dispatch');
    $request = Request::create('/api/organizations/' . self::ORGANIZATION_ID . '/equipment/export');
    $request->attributes->set('organizationId', self::ORGANIZATION_ID);
    $response = $this->createController($queryBus, $events)($request);
    self::assertSame(200, $response->getStatusCode());
  }

  #[Test]
  public function testItRefusesAnUnauthenticatedCaller(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $controller = new ExportEquipmentsController(
      queryBus: $queryBus,
      security: $security,
      csvWriter: new EquipmentCsvWriter(new SpreadsheetSafeTextAdapter()),
    );

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Authentication required.');

    $controller->__invoke(new Request());
  }

  #[Test]
  public function testItMapsAccessDeniedToForbidden(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(new EquipmentAccessDeniedException('Missing organization.equipment.read permission.'));

    $this->expectException(AccessDeniedHttpException::class);

    $this->createController($queryBus, $this->createStub(EventDispatcherPort::class))
      ->__invoke(new Request(attributes: ['organizationId' => self::ORGANIZATION_ID]));
  }

  #[Test]
  public function testItMapsOutOfScopeOrganizationToNotFound(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(EquipmentNotFoundException::forOrganizationScope(self::ORGANIZATION_ID));

    $this->expectException(NotFoundHttpException::class);

    $this->createController($queryBus, $this->createStub(EventDispatcherPort::class))
      ->__invoke(new Request(attributes: ['organizationId' => self::ORGANIZATION_ID]));
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
        EquipmentExportTooLargeException::exceedsCap(60_000, 50_000),
      ),
    ));

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $this->expectException(UnprocessableEntityHttpException::class);

    $this->createController($queryBus, $eventDispatcher)
      ->__invoke(new Request(attributes: ['organizationId' => self::ORGANIZATION_ID]));
  }

  private function createController(QueryBusPort $queryBus, EventDispatcherPort $eventDispatcher): ExportEquipmentsController
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

    return new ExportEquipmentsController(
      queryBus: $queryBus,
      security: $security,
      csvWriter: new EquipmentCsvWriter(new SpreadsheetSafeTextAdapter()),
    );
  }
}
