<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Presentation\Api\Controller;

use Auth\Infrastructure\Security\User\SecurityUser;
use Equipment\Application\Contract\Export\EquipmentExportRow;
use Equipment\Application\UseCase\Query\ExportEquipments\ExportEquipmentsResult;
use Equipment\Domain\Event\Export\EquipmentsExportedEvent;
use Equipment\Domain\Exception\{EquipmentAccessDeniedException, EquipmentExportTooLargeException, EquipmentNotFoundException};
use Equipment\Presentation\Api\Controller\ExportEquipmentsController;
use Equipment\Presentation\Api\Service\EquipmentCsvWriter;
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

    self::assertStringContainsString('type,subType,brand,model,serialNumber,locationLabel', $csv);
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
  public function testItDispatchesTheEquipmentsExportedEvent(): void
  {
    $request = new Request(attributes: ['organizationId' => self::ORGANIZATION_ID]);

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturn(new ExportEquipmentsResult(rows: [], total: 7));

    $dispatched = null;

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->willReturnCallback(static function (object $event) use (&$dispatched): void {
        $dispatched = $event;
      });

    $this->createController($queryBus, $eventDispatcher)->__invoke($request);

    self::assertInstanceOf(EquipmentsExportedEvent::class, $dispatched);
    self::assertSame(self::USER_ID, $dispatched->actorUserId);
    self::assertSame(self::ORGANIZATION_ID, $dispatched->organizationId);
    self::assertSame('csv', $dispatched->format);
    self::assertSame(7, $dispatched->rowCount);
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
      eventDispatcher: $this->createStub(EventDispatcherPort::class),
      security: $security,
      csvWriter: new EquipmentCsvWriter(),
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
      eventDispatcher: $eventDispatcher,
      security: $security,
      csvWriter: new EquipmentCsvWriter(),
    );
  }
}
