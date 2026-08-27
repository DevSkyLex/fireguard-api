<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Presentation\Api\Controller;

use Auth\Infrastructure\Security\User\SecurityUser;
use Inspection\Application\Contract\Export\NonConformityExportRow;
use Inspection\Application\Port\Outbound\{InspectionReportEntitlementPort, NonConformityReportPdfRendererPort};
use Inspection\Application\UseCase\Query\ExportNonConformities\{ExportNonConformitiesQuery, ExportNonConformitiesResult};
use Inspection\Domain\Event\Export\NonConformitiesReportExportedEvent;
use Inspection\Domain\Exception\{InspectionAccessDeniedException, InspectionExportTooLargeException, InspectionNotFoundException};
use Inspection\Presentation\Api\Controller\ExportNonConformitiesReportController;
use Inspection\Presentation\Api\Service\NonConformityExportCriteriaFactory;
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Contract\Document\OrganizationDocumentBranding;
use Organization\Application\Port\Inbound\{OrganizationAuthorizationPort, OrganizationDocumentBrandingPort};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Application\Port\Outbound\EventDispatcherPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException, UnprocessableEntityHttpException};

use function array_map;
use function str_pad;

use const STR_PAD_LEFT;

/**
 * Test ExportNonConformitiesReportControllerTest.
 *
 * The controller is thin by design: the filters are parsed by the SAME
 * `NonConformityExportCriteriaFactory` the CSV export uses (a real
 * instance here, not a mock — its enum validation is part of the
 * contract), the query is the SAME `ExportNonConformitiesQuery`, and the
 * only logic the controller owns is the severity grouping (presentation
 * shaping), the localization, the audit event and the PDF response shape.
 *
 * @category Controller Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ExportNonConformitiesReportController::class)]
final class ExportNonConformitiesReportControllerTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655510101';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655510102';

  #[Test]
  public function testItReturnsThePdfAttachment(): void
  {
    $response = $this->createController($this->queryBus())->__invoke($this->request());

    self::assertInstanceOf(Response::class, $response);
    self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    self::assertSame('application/pdf', $response->headers->get('Content-Type'));
    self::assertSame('%PDF-fake', $response->getContent());

    $disposition = $response->headers->get('Content-Disposition');
    self::assertIsString($disposition);
    self::assertStringStartsWith('attachment; filename="non-conformities-report-', $disposition);
  }

  #[Test]
  public function testItGroupsRowsBySeverityMostCriticalFirst(): void
  {
    $context = $this->renderedContext($this->queryBus());

    self::assertSame(3, $context['total']);

    /** @var list<array{severity: string, count: int, rows: list<array<string, mixed>>}> $groups */
    $groups = $context['severityGroups'];
    self::assertCount(4, $groups);

    $summary = array_map(
      static fn (array $group): array => [$group['severity'], $group['count']],
      $groups,
    );
    self::assertSame([
      ['critical', 1],
      ['high', 0],
      ['medium', 0],
      ['low', 2],
    ], $summary);

    $criticalRows = $groups[0]['rows'];
    $firstRow = $criticalRows[0];
    self::assertSame('open', $firstRow['status']);
    self::assertSame(12, $firstRow['ageInDays']);
    self::assertSame('Main Building', $firstRow['facilityName']);
    self::assertSame('SN-0042', $firstRow['equipmentSerialNumber']);
    self::assertSame('20/05/2026', $firstRow['createdAt']);
    self::assertNull($firstRow['resolvedAt']);
  }

  #[Test]
  public function testItLocalizesTheContextWithBrandingAndLanguage(): void
  {
    $context = $this->renderedContext($this->queryBus());

    self::assertSame([
      'name' => 'Acme Sécurité',
      'logoDataUri' => null,
      'legalName' => 'SAS Acme Sécurité',
      'registrationNumber' => '123 456 789',
      'vatNumber' => 'FR12345678901',
    ], $context['org']);
    self::assertSame('fr', $context['lang']);
    self::assertIsString($context['generatedAtFormatted']);
    self::assertSame('pro', $context['planKey']);
  }

  #[Test]
  public function testItForwardsTheFiltersToTheQueryAndAuditsTheirNamesOnly(): void
  {
    $askedFilters = null;

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturnCallback(
      static function (object $query) use (&$askedFilters): object {
        self::assertInstanceOf(ExportNonConformitiesQuery::class, $query);
        $askedFilters = $query->filters;

        return new ExportNonConformitiesResult(rows: [], total: 0);
      },
    );

    $dispatched = null;

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->willReturnCallback(static function (object $event) use (&$dispatched): void {
        $dispatched = $event;
      });

    $request = $this->request();
    $request->query->set('severity', 'critical');
    $request->query->set('status', 'open');

    $this->createController($queryBus, eventDispatcher: $eventDispatcher)->__invoke($request);

    self::assertSame(['severity' => 'critical', 'status' => 'open'], $askedFilters);

    self::assertInstanceOf(NonConformitiesReportExportedEvent::class, $dispatched);
    self::assertSame(self::ORGANIZATION_ID, $dispatched->organizationId);
    self::assertSame(self::USER_ID, $dispatched->actorUserId);
    self::assertSame(0, $dispatched->rowCount);
    self::assertSame(['severity', 'status'], $dispatched->filterKeys);
    self::assertSame('pro', $dispatched->planKey);
  }

  #[Test]
  public function testItRejectsAnInvalidSeverityFilter(): void
  {
    $request = $this->request();
    $request->query->set('severity', 'catastrophic');

    $this->expectException(BadRequestHttpException::class);

    $this->createController($this->queryBus())->__invoke($request);
  }

  #[Test]
  public function testItRefusesAnUnauthenticatedCaller(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Authentication required.');

    $this->createController($this->queryBus(), security: $security)->__invoke($this->request());
  }

  #[Test]
  public function testItAnswers404ForACallerOutsideTheOrganizationScope(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::OUTSIDE_SCOPE);

    $this->expectException(NotFoundHttpException::class);
    $this->expectExceptionMessage('Organization not found.');

    $this->createController($this->queryBus(), authorization: $authorization)->__invoke($this->request());
  }

  #[Test]
  public function testItAnswers403ForAMemberWithoutTheReadPermission(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::MISSING_PERMISSION);

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Missing organization.inspection.read permission.');

    $this->createController($this->queryBus(), authorization: $authorization)->__invoke($this->request());
  }

  #[Test]
  public function testItAnswers403WhenThePlanIsNotEntitled(): void
  {
    $entitlement = $this->createStub(InspectionReportEntitlementPort::class);
    $entitlement->method('isExportEntitled')->willReturn(false);

    $renderer = $this->createMock(NonConformityReportPdfRendererPort::class);
    $renderer->expects(self::never())->method('render');

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $controller = $this->createController(
      $this->queryBus(),
      renderer: $renderer,
      entitlement: $entitlement,
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('plan does not include the inspection PDF report exports');

    $controller->__invoke($this->request());
  }

  #[Test]
  public function testItMapsTheExportHandlerExceptions(): void
  {
    $cases = [
      [InspectionNotFoundException::forOrganizationScope(self::ORGANIZATION_ID), NotFoundHttpException::class],
      [new InspectionAccessDeniedException('Missing organization.inspection.read permission.'), AccessDeniedHttpException::class],
      [InspectionExportTooLargeException::exceedsCap(60000, 50000), UnprocessableEntityHttpException::class],
    ];

    foreach ($cases as [$thrown, $expected]) {
      $queryBus = $this->createStub(QueryBusPort::class);
      $queryBus->method('ask')->willThrowException($thrown);

      try {
        $this->createController($queryBus)->__invoke($this->request());
        self::fail('Expected ' . $expected . ' for ' . $thrown::class);
      } catch (NotFoundHttpException|AccessDeniedHttpException|UnprocessableEntityHttpException $caught) {
        self::assertInstanceOf($expected, $caught);
      }
    }
  }

  #[Test]
  public function testItRethrowsAnUnrecognisedQueryFailure(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(new RuntimeException('read model unavailable'));

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('read model unavailable');

    $this->createController($queryBus)->__invoke($this->request());
  }

  // #region Fixtures

  private function request(): Request
  {
    $request = new Request();
    $request->attributes->set('organizationId', self::ORGANIZATION_ID);

    return $request;
  }

  private function createController(
    QueryBusPort $queryBus,
    ?NonConformityReportPdfRendererPort $renderer = null,
    ?OrganizationAuthorizationPort $authorization = null,
    ?InspectionReportEntitlementPort $entitlement = null,
    ?EventDispatcherPort $eventDispatcher = null,
    ?Security $security = null,
  ): ExportNonConformitiesReportController {
    if (null === $security) {
      $security = $this->createStub(Security::class);
      $security->method('getUser')->willReturn(new SecurityUser(
        id: self::USER_ID,
        email: 'inspector@example.com',
        password: 'hashed-password',
        roles: ['ROLE_USER'],
        scopes: [],
        isActive: true,
      ));
    }

    if (null === $renderer) {
      $renderer = $this->createStub(NonConformityReportPdfRendererPort::class);
      $renderer->method('render')->willReturn('%PDF-fake');
    }

    if (null === $authorization) {
      $authorization = $this->createStub(OrganizationAuthorizationPort::class);
      $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);
    }

    if (null === $entitlement) {
      $entitlement = $this->createStub(InspectionReportEntitlementPort::class);
      $entitlement->method('isExportEntitled')->willReturn(true);
      $entitlement->method('resolvePlanKey')->willReturn('pro');
    }

    return new ExportNonConformitiesReportController(
      queryBus: $queryBus,
      authorization: $authorization,
      branding: $this->brandingPort(),
      entitlement: $entitlement,
      renderer: $renderer,
      criteriaFactory: new NonConformityExportCriteriaFactory(),
      eventDispatcher: $eventDispatcher ?? $this->createStub(EventDispatcherPort::class),
      security: $security,
    );
  }

  /**
   * Renders the request and captures the exact Twig context the controller
   * built, by intercepting the renderer.
   *
   * @return array<string, mixed>
   */
  private function renderedContext(QueryBusPort $queryBus): array
  {
    $context = null;

    /** @var NonConformityReportPdfRendererPort&MockObject $renderer */
    $renderer = $this->createMock(NonConformityReportPdfRendererPort::class);
    $renderer->expects(self::once())
      ->method('render')
      ->willReturnCallback(static function (array $received) use (&$context): string {
        /** @var array<string, mixed> $received */
        $context = $received;

        return '%PDF-fake';
      });

    $this->createController($queryBus, renderer: $renderer)->__invoke($this->request());

    self::assertIsArray($context);

    return $context;
  }

  private function brandingPort(): OrganizationDocumentBrandingPort
  {
    $branding = $this->createStub(OrganizationDocumentBrandingPort::class);
    $branding->method('getDocumentBranding')->willReturn(new OrganizationDocumentBranding(
      organizationName: 'Acme Sécurité',
      logoDataUri: null,
      legalName: 'SAS Acme Sécurité',
      registrationNumber: '123 456 789',
      vatNumber: 'FR12345678901',
      timezone: 'Europe/Paris',
      locale: 'fr-FR',
      dateFormat: 'dd/MM/yyyy',
    ));

    return $branding;
  }

  private function queryBus(): QueryBusPort
  {
    $rows = [
      $this->row(severity: 'low', status: 'done', ageInDays: 40, resolvedAt: '2026-05-30T00:00:00+00:00'),
      $this->row(severity: 'critical', status: 'open', ageInDays: 12),
      $this->row(severity: 'low', status: 'open', ageInDays: 3),
    ];

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturn(new ExportNonConformitiesResult(rows: $rows, total: 3));

    return $queryBus;
  }

  private function row(string $severity, string $status, int $ageInDays, ?string $resolvedAt = null): NonConformityExportRow
  {
    return new NonConformityExportRow(
      id: '550e8400-e29b-41d4-a716-4466555101' . str_pad((string) $ageInDays, 2, '0', STR_PAD_LEFT),
      severity: $severity,
      status: $status,
      ageInDays: $ageInDays,
      facilityId: '550e8400-e29b-41d4-a716-446655510201',
      facilityName: 'Main Building',
      equipmentId: '550e8400-e29b-41d4-a716-446655510202',
      equipmentSerialNumber: 'SN-0042',
      inspectionId: '550e8400-e29b-41d4-a716-446655510203',
      createdAt: '2026-05-20T00:00:00+00:00',
      resolvedAt: $resolvedAt,
    );
  }
  // #endregion
}
