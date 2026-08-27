<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Presentation\Api\Controller;

use Auth\Infrastructure\Security\User\SecurityUser;
use Facility\Application\Contract\Export\FacilityExportRow;
use Facility\Application\UseCase\Query\ExportFacilities\ExportFacilitiesResult;
use Facility\Domain\Event\Export\FacilitiesExportedEvent;
use Facility\Domain\Exception\{FacilityAccessDeniedException, FacilityExportTooLargeException, FacilityNotFoundException};
use Facility\Presentation\Api\Controller\ExportFacilitiesController;
use Facility\Presentation\Api\Service\{FacilityCsvWriter, FacilityExportCriteriaFactory};
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
 * Test ExportFacilitiesControllerTest.
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
#[CoversClass(ExportFacilitiesController::class)]
final class ExportFacilitiesControllerTest extends TestCase
{
  private const string USER_ID = '550e8400-e29b-41d4-a716-446655508001';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655508002';

  #[Test]
  public function testItStreamsTheMatchingFacilitiesAsCsv(): void
  {
    $request = new Request(attributes: ['organizationId' => self::ORGANIZATION_ID]);

    $row = new FacilityExportRow(
      id: 'facility-1',
      type: 'building',
      name: 'Main Building',
      code: 'BLD-1',
      address: '1 Rue de Paris',
      latitude: 48.8566,
      longitude: 2.3522,
      parentCode: 'SITE-1',
      status: 'active',
      createdAt: '2026-08-01T00:00:00+00:00',
      updatedAt: '2026-08-02T00:00:00+00:00',
    );

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturn(new ExportFacilitiesResult(rows: [$row], total: 1));

    $response = $this->createController($queryBus, $this->createStub(EventDispatcherPort::class))->__invoke($request);

    self::assertInstanceOf(StreamedResponse::class, $response);
    self::assertSame('text/csv; charset=utf-8', $response->headers->get('Content-Type'));
    self::assertSame('no', $response->headers->get('X-Accel-Buffering'));

    $disposition = (string) $response->headers->get('Content-Disposition');
    self::assertStringStartsWith('attachment; filename="facilities-export-', $disposition);
    self::assertStringEndsWith('.csv"', $disposition);

    ob_start();
    $response->sendContent();
    $csv = (string) ob_get_clean();

    self::assertStringContainsString('type,name,code,address,latitude,longitude,parentCode,id,status,createdAt,updatedAt', $csv);
    self::assertStringContainsString('Main Building', $csv);
    self::assertStringContainsString('SITE-1', $csv);
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
  public function testItAuditsTheExportWithFilterNamesOnly(): void
  {
    $request = new Request(
      query: ['status' => 'active', 'type' => 'building'],
      attributes: ['organizationId' => self::ORGANIZATION_ID],
    );

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturn(new ExportFacilitiesResult(rows: [], total: 7));

    $dispatched = null;

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->willReturnCallback(static function (object $event) use (&$dispatched): void {
        $dispatched = $event;
      });

    $this->createController($queryBus, $eventDispatcher)->__invoke($request);

    self::assertInstanceOf(FacilitiesExportedEvent::class, $dispatched);
    self::assertSame(self::USER_ID, $dispatched->actorUserId);
    self::assertSame(self::ORGANIZATION_ID, $dispatched->organizationId);
    self::assertSame('csv', $dispatched->format);
    self::assertSame(7, $dispatched->rowCount);
    self::assertSame(['type', 'status'], $dispatched->filterKeys);
  }

  #[Test]
  public function testItRefusesAnUnauthenticatedCaller(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $controller = new ExportFacilitiesController(
      queryBus: $queryBus,
      eventDispatcher: $this->createStub(EventDispatcherPort::class),
      security: $security,
      criteriaFactory: new FacilityExportCriteriaFactory(),
      csvWriter: new FacilityCsvWriter(),
    );

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Authentication required.');

    $controller->__invoke(new Request());
  }

  #[Test]
  public function testItMapsAccessDeniedToForbidden(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(new FacilityAccessDeniedException('Missing organization.facilities.read permission.'));

    $this->expectException(AccessDeniedHttpException::class);

    $this->createController($queryBus, $this->createStub(EventDispatcherPort::class))
      ->__invoke(new Request(attributes: ['organizationId' => self::ORGANIZATION_ID]));
  }

  #[Test]
  public function testItMapsOutOfScopeOrganizationToNotFound(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(FacilityNotFoundException::forOrganizationScope(self::ORGANIZATION_ID));

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
        FacilityExportTooLargeException::exceedsCap(60_000, 50_000),
      ),
    ));

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $this->expectException(UnprocessableEntityHttpException::class);

    $this->createController($queryBus, $eventDispatcher)
      ->__invoke(new Request(attributes: ['organizationId' => self::ORGANIZATION_ID]));
  }

  private function createController(QueryBusPort $queryBus, EventDispatcherPort $eventDispatcher): ExportFacilitiesController
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

    return new ExportFacilitiesController(
      queryBus: $queryBus,
      eventDispatcher: $eventDispatcher,
      security: $security,
      criteriaFactory: new FacilityExportCriteriaFactory(),
      csvWriter: new FacilityCsvWriter(),
    );
  }
}
