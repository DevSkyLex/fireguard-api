<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Presentation\Api\Controller;

use Auth\Infrastructure\Security\User\SecurityUser;
use Inspection\Application\Contract\Event\Export\NonConformitiesExportedEvent;
use Inspection\Application\Contract\Export\NonConformityExportRow;
use Inspection\Application\UseCase\Query\ExportNonConformities\ExportNonConformitiesResult;
use Inspection\Domain\Exception\{InspectionAccessDeniedException, InspectionExportTooLargeException, InspectionNotFoundException};
use Inspection\Presentation\Api\Controller\ExportNonConformitiesController;
use Inspection\Presentation\Api\Service\NonConformityExportCriteriaFactory;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Application\Port\Outbound\EventDispatcherPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException, UnprocessableEntityHttpException};

use function ob_get_clean;
use function ob_start;

/**
 * Test ExportNonConformitiesControllerTest.
 *
 * Mirrors `ExportInspectionsControllerTest` and
 * `Intervention\...\ExportInterventionsControllerTest`.
 *
 * @category Controller Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ExportNonConformitiesController::class)]
#[CoversClass(\Inspection\Application\UseCase\Command\Export\ExportNonConformitiesDocument\ExportNonConformitiesDocumentHandler::class)]
final class ExportNonConformitiesControllerTest extends TestCase
{
  private const string USER_ID = '550e8400-e29b-41d4-a716-446655509001';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655509002';

  #[Test]
  public function testItStreamsTheMatchingNonConformitiesAsCsv(): void
  {
    $request = $this->requestWithOrganization();

    $row = new NonConformityExportRow(
      id: 'nc-1',
      severity: 'critical',
      status: 'open',
      ageInDays: 10,
      facilityId: 'facility-1',
      facilityName: 'Main Warehouse',
      equipmentId: 'equipment-1',
      equipmentSerialNumber: 'SN-001',
      inspectionId: 'inspection-1',
      createdAt: '2026-08-01T00:00:00+00:00',
      resolvedAt: null,
    );

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturn(new ExportNonConformitiesResult(rows: [$row], total: 1));

    $response = $this->createController($queryBus, $this->createStub(EventDispatcherPort::class))->__invoke($request);

    self::assertSame('text/csv; charset=utf-8', $response->headers->get('Content-Type'));
    self::assertSame('no', $response->headers->get('X-Accel-Buffering'));

    $disposition = (string) $response->headers->get('Content-Disposition');
    self::assertStringStartsWith('attachment; filename="non-conformities-export-', $disposition);
    self::assertStringEndsWith('.csv"', $disposition);

    ob_start();
    $response->sendContent();
    $csv = (string) ob_get_clean();

    self::assertStringContainsString('id,severity,status,age_in_days,facility,equipment,inspection_id,created_at,resolved_at', $csv);
    self::assertStringContainsString('Main Warehouse', $csv);
    self::assertStringContainsString('SN-001', $csv);
  }

  #[Test]
  public function testItRejectsARequestWithoutTheOrganizationIdRouteAttribute(): void
  {
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('OrganizationId URI parameter is required.');

    $this->createController($queryBus, $this->createStub(EventDispatcherPort::class))->__invoke(new Request());
  }

  #[Test]
  public function testItDispatchesTheExportEventWithFilterNamesOnly(): void
  {
    $request = $this->requestWithOrganization(['severity' => 'critical', 'status' => 'open']);

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturn(new ExportNonConformitiesResult(rows: [], total: 4));

    $dispatched = null;

    /** @var EventDispatcherPort&MockObject $commandBus */
    $commandBus = $this->createMock(EventDispatcherPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willReturnCallback(static function (object $event) use (&$dispatched): void {
        $dispatched = $event;


      });

    $this->createController($queryBus, $commandBus)->__invoke($request);

    self::assertInstanceOf(NonConformitiesExportedEvent::class, $dispatched);
    self::assertSame(self::USER_ID, $dispatched->actorUserId);
    self::assertSame(self::ORGANIZATION_ID, $dispatched->organizationId);
    self::assertSame('csv', $dispatched->format);
    self::assertSame(4, $dispatched->rowCount);
    self::assertSame(['severity', 'status'], $dispatched->filterKeys);
  }

  #[Test]
  public function testItRefusesAnUnauthenticatedCaller(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $controller = new ExportNonConformitiesController(commandBus: $this->documents(new \Inspection\Application\UseCase\Command\Export\ExportNonConformitiesDocument\ExportNonConformitiesDocumentHandler(queryBus: $queryBus, csvRenderer: new \Inspection\Infrastructure\Adapter\Export\NonConformityCsvRendererAdapter(), events: $this->createStub(EventDispatcherPort::class), transactions: $this->transactions())), security: $security, criteriaFactory: new NonConformityExportCriteriaFactory());

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Authentication required.');

    $controller->__invoke(new Request());
  }

  #[Test]
  public function testItMapsAccessDeniedToForbidden(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(new InspectionAccessDeniedException('Missing organization.inspection.read permission.'));

    $this->expectException(AccessDeniedHttpException::class);

    $this->createController($queryBus, $this->createStub(EventDispatcherPort::class))->__invoke($this->requestWithOrganization());
  }

  #[Test]
  public function testItMapsOutOfScopeOrganizationToNotFound(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(InspectionNotFoundException::forOrganizationScope(self::ORGANIZATION_ID));

    $this->expectException(NotFoundHttpException::class);

    $this->createController($queryBus, $this->createStub(EventDispatcherPort::class))->__invoke($this->requestWithOrganization());
  }

  #[Test]
  public function testItRefusesAnExportExceedingTheRowCapThroughTheBusWrapping(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(new RuntimeException(
      'Query handling failed.',
      0,
      new RuntimeException(
        'Handler failed.',
        0,
        InspectionExportTooLargeException::exceedsCap(60_000, 50_000),
      ),
    ));

    /** @var EventDispatcherPort&MockObject $commandBus */
    $commandBus = $this->createMock(EventDispatcherPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $this->expectException(UnprocessableEntityHttpException::class);

    $this->createController($queryBus, $commandBus)->__invoke($this->requestWithOrganization());
  }

  /**
   * @param array<string, string> $query
   */
  private function requestWithOrganization(array $query = []): Request
  {
    $request = new Request($query);
    $request->attributes->set('organizationId', self::ORGANIZATION_ID);

    return $request;
  }

  private function createController(QueryBusPort $queryBus, EventDispatcherPort $commandBus): ExportNonConformitiesController
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

    return new ExportNonConformitiesController(commandBus: $this->documents(new \Inspection\Application\UseCase\Command\Export\ExportNonConformitiesDocument\ExportNonConformitiesDocumentHandler(queryBus: $queryBus, csvRenderer: new \Inspection\Infrastructure\Adapter\Export\NonConformityCsvRendererAdapter(), events: $commandBus, transactions: $this->transactions())), security: $security, criteriaFactory: new NonConformityExportCriteriaFactory());
  }

  private function documents(\Inspection\Application\UseCase\Command\Export\ExportNonConformitiesDocument\ExportNonConformitiesDocumentHandler $handler): \Shared\Application\Port\Inbound\CommandBusPort
  {
    $documents = $this->createStub(\Shared\Application\Port\Inbound\CommandBusPort::class);
    $documents->method('dispatch')->willReturnCallback($handler(...));

    return $documents;
  }

  private function transactions(): \Shared\Application\Port\Outbound\TransactionManagerPort
  {
    $transactions = $this->createStub(\Shared\Application\Port\Outbound\TransactionManagerPort::class);
    $transactions->method('transactional')->willReturnCallback(static fn (callable $operation): mixed => $operation());

    return $transactions;
  }
}
