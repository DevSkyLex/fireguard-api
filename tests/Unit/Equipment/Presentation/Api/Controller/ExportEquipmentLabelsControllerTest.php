<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Presentation\Api\Controller;

use Auth\Infrastructure\Security\User\SecurityUser;
use Equipment\Application\Contract\Export\EquipmentExportRow;
use Equipment\Application\Port\Outbound\EquipmentLabelSheetPdfRendererPort;
use Equipment\Application\UseCase\Query\ExportEquipmentLabels\ExportEquipmentLabelsResult;
use Equipment\Domain\Event\Export\EquipmentLabelsExportedEvent;
use Equipment\Domain\Exception\{EquipmentAccessDeniedException, EquipmentLabelExportTooLargeException, EquipmentNotFoundException};
use Equipment\Presentation\Api\Controller\ExportEquipmentLabelsController;
use Organization\Application\Contract\Document\OrganizationDocumentBranding;
use Organization\Application\Port\Inbound\OrganizationDocumentBrandingPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Application\Port\Outbound\EventDispatcherPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException, UnprocessableEntityHttpException};

/**
 * Test ExportEquipmentLabelsControllerTest.
 *
 * Mirrors `ExportEquipmentsControllerTest` — the selection parsing, the
 * QR value shaping (the canonical `/api/equipment/{id}` IRI the field scan
 * resolves verbatim), and the bus-wrapped exception mapping are the
 * boundaries this controller owns.
 *
 * @category Controller Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ExportEquipmentLabelsController::class)]
final class ExportEquipmentLabelsControllerTest extends TestCase
{
  private const string USER_ID = '550e8400-e29b-41d4-a716-446655570001';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655570002';

  private const string EQUIPMENT_ID = '550e8400-e29b-41d4-a716-446655570003';

  #[Test]
  public function testItRendersThePdfWithTheCanonicalEquipmentIriAsQrValue(): void
  {
    $request = new Request(attributes: ['organizationId' => self::ORGANIZATION_ID]);

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturn(new ExportEquipmentLabelsResult(
      rows: [$this->createRow()],
      total: 1,
      selection: 'organization',
    ));

    $capturedContext = null;

    /** @var EquipmentLabelSheetPdfRendererPort&MockObject $renderer */
    $renderer = $this->createMock(EquipmentLabelSheetPdfRendererPort::class);
    $renderer->expects(self::once())
      ->method('render')
      ->willReturnCallback(static function (array $context) use (&$capturedContext): string {
        $capturedContext = $context;

        return '%PDF-fake';
      });

    $response = $this->createController($queryBus, $this->createStub(EventDispatcherPort::class), $renderer)
      ->__invoke($request);

    self::assertSame(200, $response->getStatusCode());
    self::assertSame('application/pdf', $response->headers->get('Content-Type'));
    self::assertSame('%PDF-fake', $response->getContent());

    $disposition = (string) $response->headers->get('Content-Disposition');
    self::assertStringStartsWith('attachment; filename="equipment-labels-', $disposition);
    self::assertStringEndsWith('.pdf"', $disposition);

    self::assertIsArray($capturedContext);

    /** @var array{lang: string, labels: list<array<string, ?string>>} $capturedContext */
    self::assertSame('en', $capturedContext['lang']);
    self::assertCount(1, $capturedContext['labels']);
    self::assertSame('/api/equipment/' . self::EQUIPMENT_ID, $capturedContext['labels'][0]['qrValue']);
    self::assertSame('fire_extinguisher', $capturedContext['labels'][0]['type']);
    self::assertSame('SN-1', $capturedContext['labels'][0]['serialNumber']);
    self::assertSame('Main Warehouse', $capturedContext['labels'][0]['facilityName']);
  }

  #[Test]
  public function testItDispatchesTheLabelsExportedEvent(): void
  {
    $request = new Request(attributes: ['organizationId' => self::ORGANIZATION_ID]);

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturn(new ExportEquipmentLabelsResult(rows: [], total: 7, selection: 'facility'));

    $dispatched = null;

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->willReturnCallback(static function (object $event) use (&$dispatched): void {
        $dispatched = $event;
      });

    $this->createController($queryBus, $eventDispatcher)->__invoke($request);

    self::assertInstanceOf(EquipmentLabelsExportedEvent::class, $dispatched);
    self::assertSame(self::USER_ID, $dispatched->actorUserId);
    self::assertSame(self::ORGANIZATION_ID, $dispatched->organizationId);
    self::assertSame('facility', $dispatched->selection);
    self::assertSame(7, $dispatched->labelCount);
  }

  #[Test]
  public function testItRefusesAnUnauthenticatedCaller(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $controller = new ExportEquipmentLabelsController(
      queryBus: $queryBus,
      branding: $this->createStub(OrganizationDocumentBrandingPort::class),
      renderer: $this->createStub(EquipmentLabelSheetPdfRendererPort::class),
      eventDispatcher: $this->createStub(EventDispatcherPort::class),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Authentication required.');

    $controller->__invoke(new Request());
  }

  #[Test]
  public function testItRejectsProvidingBothSelectionModes(): void
  {
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $request = new Request(
      query: ['ids' => [self::EQUIPMENT_ID], 'facilityId' => '550e8400-e29b-41d4-a716-446655570004'],
      attributes: ['organizationId' => self::ORGANIZATION_ID],
    );

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('Provide either ids[] or facilityId, not both.');

    $this->createController($queryBus, $this->createStub(EventDispatcherPort::class))->__invoke($request);
  }

  #[Test]
  public function testItRejectsAnEmptyIdsParameter(): void
  {
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $request = new Request(
      query: ['ids' => []],
      attributes: ['organizationId' => self::ORGANIZATION_ID],
    );

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('ids[] must be a non-empty list of equipment identifiers.');

    $this->createController($queryBus, $this->createStub(EventDispatcherPort::class))->__invoke($request);
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
  public function testItRefusesASelectionExceedingTheLabelCapThroughTheBusWrapping(): void
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
        EquipmentLabelExportTooLargeException::exceedsCap(600, 500),
      ),
    ));

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $this->expectException(UnprocessableEntityHttpException::class);

    $this->createController($queryBus, $eventDispatcher)
      ->__invoke(new Request(attributes: ['organizationId' => self::ORGANIZATION_ID]));
  }

  private function createController(
    QueryBusPort $queryBus,
    EventDispatcherPort $eventDispatcher,
    ?EquipmentLabelSheetPdfRendererPort $renderer = null,
  ): ExportEquipmentLabelsController {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(new SecurityUser(
      id: self::USER_ID,
      email: 'labels@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    ));

    $branding = $this->createStub(OrganizationDocumentBrandingPort::class);
    $branding->method('getDocumentBranding')->willReturn(new OrganizationDocumentBranding(
      organizationName: 'Acme Corp',
      logoDataUri: null,
      legalName: null,
      registrationNumber: null,
      vatNumber: null,
      timezone: 'UTC',
      locale: 'en-US',
      dateFormat: 'Y-m-d',
    ));

    if (null === $renderer) {
      $rendererStub = $this->createStub(EquipmentLabelSheetPdfRendererPort::class);
      $rendererStub->method('render')->willReturn('%PDF-fake');
      $renderer = $rendererStub;
    }

    return new ExportEquipmentLabelsController(
      queryBus: $queryBus,
      branding: $branding,
      renderer: $renderer,
      eventDispatcher: $eventDispatcher,
      security: $security,
    );
  }

  private function createRow(): EquipmentExportRow
  {
    return new EquipmentExportRow(
      id: self::EQUIPMENT_ID,
      type: 'fire_extinguisher',
      subType: 'CO2',
      brand: 'Acme',
      model: 'X100',
      serialNumber: 'SN-1',
      locationLabel: 'Hallway',
      status: 'operational',
      facilityId: '550e8400-e29b-41d4-a716-446655570004',
      facilityName: 'Main Warehouse',
      installedAt: '2026-01-01T00:00:00+00:00',
      commissionedAt: '2026-01-02T00:00:00+00:00',
      createdAt: '2026-01-01T00:00:00+00:00',
      updatedAt: '2026-01-02T00:00:00+00:00',
    );
  }
}
