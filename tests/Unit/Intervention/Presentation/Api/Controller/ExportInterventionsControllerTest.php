<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Presentation\Api\Controller;

use Auth\Infrastructure\Security\User\SecurityUser;
use Intervention\Application\Contract\Event\Export\InterventionsExportedEvent;
use Intervention\Application\Contract\Export\InterventionExportRow;
use Intervention\Application\UseCase\Query\ExportInterventions\ExportInterventionsResult;
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionNotFoundException};
use Intervention\Domain\Exception\InterventionExportTooLargeException;
use Intervention\Presentation\Api\Controller\ExportInterventionsController;
use Intervention\Presentation\Api\Service\InterventionExportCriteriaFactory;
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
 * Test ExportInterventionsControllerTest.
 *
 * Mirrors `Audit\...\ExportAuditEventsControllerTest` — the CSV body itself
 * is asserted here, at the controller unit-test level, since
 * `StreamedResponse::getContent()` is not reliably buffered by the
 * functional `KernelBrowser` test client.
 *
 * @category Controller Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ExportInterventionsController::class)]
#[CoversClass(\Intervention\Application\UseCase\Command\Export\ExportInterventionsDocument\ExportInterventionsDocumentHandler::class)]
final class ExportInterventionsControllerTest extends TestCase
{
  private const string USER_ID = '550e8400-e29b-41d4-a716-446655507001';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655507002';

  #[Test]
  public function testItStreamsTheMatchingInterventionsAsCsv(): void
  {
    $request = new Request(['organization' => '/api/organizations/' . self::ORGANIZATION_ID]);

    $row = new InterventionExportRow(
      id: 'intervention-1',
      name: 'Fire panel check',
      type: 'inspection_campaign',
      status: 'planned',
      priority: 'high',
      siteId: 'site-1',
      siteName: 'Main Warehouse',
      responsibleId: 'member-1',
      responsibleName: 'Jane Doe',
      dueAt: '2026-09-01T00:00:00+00:00',
      createdAt: '2026-08-01T00:00:00+00:00',
      updatedAt: '2026-08-02T00:00:00+00:00',
    );

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturn(new ExportInterventionsResult(rows: [$row], total: 1));

    $response = $this->createController($queryBus, $this->createStub(EventDispatcherPort::class))->__invoke($request);

    self::assertSame('text/csv; charset=utf-8', $response->headers->get('Content-Type'));
    self::assertSame('no', $response->headers->get('X-Accel-Buffering'));

    $disposition = (string) $response->headers->get('Content-Disposition');
    self::assertStringStartsWith('attachment; filename="interventions-export-', $disposition);
    self::assertStringEndsWith('.csv"', $disposition);

    ob_start();
    $response->sendContent();
    $csv = (string) ob_get_clean();

    self::assertStringContainsString('id,name,type,status,priority,facility,assignee,due_at,created_at,updated_at', $csv);
    self::assertStringContainsString('Fire panel check', $csv);
    self::assertStringContainsString('Main Warehouse', $csv);
    self::assertStringContainsString('Jane Doe', $csv);
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
      'status' => 'draft',
      'responsible' => '/api/organizations/' . self::ORGANIZATION_ID . '/members/a-very-identifying-member-id',
    ]);

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturn(new ExportInterventionsResult(rows: [], total: 7));

    $dispatched = null;

    /** @var EventDispatcherPort&MockObject $commandBus */
    $commandBus = $this->createMock(EventDispatcherPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willReturnCallback(static function (object $event) use (&$dispatched): void {
        $dispatched = $event;


      });

    $this->createController($queryBus, $commandBus)->__invoke($request);

    self::assertInstanceOf(InterventionsExportedEvent::class, $dispatched);
    self::assertSame(self::USER_ID, $dispatched->actorUserId);
    self::assertSame(self::ORGANIZATION_ID, $dispatched->organizationId);
    self::assertSame('csv', $dispatched->format);
    self::assertSame(7, $dispatched->rowCount);
    self::assertSame(['status', 'responsibleId'], $dispatched->filterKeys);
  }

  #[Test]
  public function testItRefusesAnUnauthenticatedCaller(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $controller = new ExportInterventionsController(commandBus: $this->documents(new \Intervention\Application\UseCase\Command\Export\ExportInterventionsDocument\ExportInterventionsDocumentHandler(queryBus: $queryBus, csvRenderer: new \Intervention\Infrastructure\Adapter\Export\InterventionCsvRendererAdapter(new SpreadsheetSafeTextAdapter()), events: $this->createStub(EventDispatcherPort::class), transactions: $this->transactions(), authorization: $this->authorization())), security: $security, criteriaFactory: new InterventionExportCriteriaFactory());

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Authentication required.');

    $controller->__invoke(new Request());
  }

  #[Test]
  public function testItMapsAccessDeniedToForbidden(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(new InterventionAccessDeniedException('Missing organization.interventions.read permission.'));

    $this->expectException(AccessDeniedHttpException::class);

    $this->createController($queryBus, $this->createStub(EventDispatcherPort::class))
      ->__invoke(new Request(['organization' => '/api/organizations/' . self::ORGANIZATION_ID]));
  }

  #[Test]
  public function testItMapsOutOfScopeOrganizationToNotFound(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(InterventionNotFoundException::forOrganizationScope(self::ORGANIZATION_ID));

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
        InterventionExportTooLargeException::exceedsCap(60_000, 50_000),
      ),
    ));

    /** @var EventDispatcherPort&MockObject $commandBus */
    $commandBus = $this->createMock(EventDispatcherPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $this->expectException(UnprocessableEntityHttpException::class);

    $this->createController($queryBus, $commandBus)
      ->__invoke(new Request(['organization' => '/api/organizations/' . self::ORGANIZATION_ID]));
  }

  private function createController(QueryBusPort $queryBus, EventDispatcherPort $commandBus): ExportInterventionsController
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

    return new ExportInterventionsController(commandBus: $this->documents(new \Intervention\Application\UseCase\Command\Export\ExportInterventionsDocument\ExportInterventionsDocumentHandler(queryBus: $queryBus, csvRenderer: new \Intervention\Infrastructure\Adapter\Export\InterventionCsvRendererAdapter(new SpreadsheetSafeTextAdapter()), events: $commandBus, transactions: $this->transactions(), authorization: $this->authorization())), security: $security, criteriaFactory: new InterventionExportCriteriaFactory());
  }

  private function documents(\Intervention\Application\UseCase\Command\Export\ExportInterventionsDocument\ExportInterventionsDocumentHandler $handler): \Shared\Application\Port\Inbound\CommandBusPort
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

  private function authorization(): \Organization\Application\Port\Inbound\OrganizationAuthorizationPort
  {
    $authorization = $this->createStub(\Organization\Application\Port\Inbound\OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(\Organization\Application\Contract\Authorization\OrganizationAccessDecision::GRANTED);

    return $authorization;
  }
}
