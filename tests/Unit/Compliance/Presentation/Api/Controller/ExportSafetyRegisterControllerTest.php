<?php

declare(strict_types=1);

namespace Tests\Unit\Compliance\Presentation\Api\Controller;

use Auth\Infrastructure\Security\User\SecurityUser;
use Compliance\Application\Contract\FacilityComplianceView;
use Compliance\Application\Port\Outbound\{ComplianceExportEntitlementPort, SafetyRegisterPdfRendererPort};
use Compliance\Application\Service\SafetyRegisterContextBuilder;
use Compliance\Application\UseCase\Query\GetComplianceOverview\{GetComplianceOverviewQuery, GetComplianceOverviewResult};
use Compliance\Application\UseCase\Query\GetFacilityCompliance\{GetFacilityComplianceQuery, GetFacilityComplianceResult};
use Compliance\Domain\Event\SafetyRegisterExportedEvent;
use Compliance\Domain\ValueObject\ComplianceStatus;
use Compliance\Presentation\Api\Controller\ExportSafetyRegisterController;
use Organization\Application\Contract\Document\OrganizationDocumentBranding;
use Organization\Application\Port\Inbound\{OrganizationAuthorizationPort, OrganizationDocumentBrandingPort};
use Organization\Domain\Exception\OrganizationAccessDeniedException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Application\Port\Outbound\EventDispatcherPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Test ExportSafetyRegisterControllerTest.
 *
 * The "registre de sécurité" is a regulatory document, so three gates guard
 * it and each has to hold independently: authentication, the
 * `organization.compliance.export` permission, and the plan entitlement. The
 * export is also itself an audited event, tagged with the scope it covered.
 *
 * @category Controller Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ExportSafetyRegisterController::class)]
final class ExportSafetyRegisterControllerTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655507001';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655507002';

  private const string FACILITY_ID = '550e8400-e29b-41d4-a716-446655507003';

  #[Test]
  public function testItReturnsTheOrganizationWideRegisterAsAPdfAttachment(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturn($this->overviewResult());

    $response = $this->createController($queryBus)->__invoke($this->request());

    self::assertInstanceOf(Response::class, $response);
    self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    self::assertSame('application/pdf', $response->headers->get('Content-Type'));
    self::assertSame('%PDF-fake', $response->getContent());

    $disposition = (string) $response->headers->get('Content-Disposition');
    self::assertStringStartsWith('attachment; filename="registre-securite-' . self::ORGANIZATION_ID . '-', $disposition);
    self::assertStringEndsWith('.pdf"', $disposition);
  }

  #[Test]
  public function testItRendersTheOrganizationScopedContext(): void
  {
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(GetComplianceOverviewQuery::class))
      ->willReturn($this->overviewResult());

    $context = null;

    /** @var SafetyRegisterPdfRendererPort&MockObject $renderer */
    $renderer = $this->createMock(SafetyRegisterPdfRendererPort::class);
    $renderer->expects(self::once())
      ->method('render')
      ->willReturnCallback(static function (array $received) use (&$context): string {
        $context = $received;

        return '%PDF-fake';
      });

    $this->createController($queryBus, renderer: $renderer)->__invoke($this->request());

    self::assertIsArray($context);
    self::assertSame('organization', $context['scope']);
    self::assertSame(self::ORGANIZATION_ID, $context['organizationId']);
    self::assertNull($context['facilityId']);
    self::assertSame('pro', $context['planKey']);
    self::assertSame('2026-06-01T08:00:00+00:00', $context['generatedAt']);
    self::assertSame('fr', $context['lang']);
    self::assertSame('01/06/2026 10:00', $context['generatedAtFormatted']);
    self::assertSame([
      'name' => 'Acme Sécurité',
      'logoDataUri' => null,
      'legalName' => 'SAS Acme Sécurité',
      'registrationNumber' => '123 456 789',
      'vatNumber' => 'FR12345678901',
    ], $context['org']);
    $facilities = $context['facilities'];
    self::assertIsArray($facilities);
    $firstFacility = $facilities[0];
    self::assertIsArray($firstFacility);
    self::assertSame('20/05/2026', $firstFacility['lastInspectionAt']);
  }

  #[Test]
  public function testItRendersTheFacilityScopedContextWhenTheRouteCarriesAFacility(): void
  {
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(GetFacilityComplianceQuery::class))
      ->willReturn($this->facilityComplianceResult());

    $context = null;

    /** @var SafetyRegisterPdfRendererPort&MockObject $renderer */
    $renderer = $this->createMock(SafetyRegisterPdfRendererPort::class);
    $renderer->expects(self::once())
      ->method('render')
      ->willReturnCallback(static function (array $received) use (&$context): string {
        $context = $received;

        return '%PDF-fake';
      });

    $response = $this->createController($queryBus, renderer: $renderer)
      ->__invoke($this->request(self::FACILITY_ID));

    self::assertIsArray($context);
    self::assertSame('facility', $context['scope']);
    self::assertSame(self::FACILITY_ID, $context['facilityId']);
    self::assertStringContainsString('registre-securite-' . self::FACILITY_ID, (string) $response->headers->get('Content-Disposition'));
  }

  #[Test]
  public function testItAuditsTheExport(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturn($this->overviewResult());

    $dispatched = null;

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->willReturnCallback(static function (object $event) use (&$dispatched): void {
        $dispatched = $event;
      });

    $this->createController($queryBus, eventDispatcher: $eventDispatcher)->__invoke($this->request());

    self::assertInstanceOf(SafetyRegisterExportedEvent::class, $dispatched);
    self::assertSame(self::ORGANIZATION_ID, $dispatched->organizationId);
    self::assertNull($dispatched->facilityId);
    self::assertSame(self::USER_ID, $dispatched->actorUserId);
    self::assertSame('pro', $dispatched->planKey);
    self::assertSame('organization', $dispatched->scope);
    self::assertSame('2026-06-01T08:00:00+00:00', $dispatched->generatedAt);
  }

  #[Test]
  public function testItFallsBackToAnUnknownPlanKey(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturn($this->overviewResult());

    $entitlement = $this->createStub(ComplianceExportEntitlementPort::class);
    $entitlement->method('isExportEntitled')->willReturn(true);
    $entitlement->method('resolvePlanKey')->willReturn(null);

    $dispatched = null;

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->willReturnCallback(static function (object $event) use (&$dispatched): void {
        $dispatched = $event;
      });

    $this->createController($queryBus, entitlement: $entitlement, eventDispatcher: $eventDispatcher)
      ->__invoke($this->request());

    self::assertInstanceOf(SafetyRegisterExportedEvent::class, $dispatched);
    self::assertSame('unknown', $dispatched->planKey);
  }

  #[Test]
  public function testItRefusesAnUnauthenticatedCaller(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $renderer = $this->createMock(SafetyRegisterPdfRendererPort::class);
    $renderer->expects(self::never())->method('render');

    $controller = new ExportSafetyRegisterController(
      queryBus: $this->createStub(QueryBusPort::class),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      branding: $this->brandingPort(),
      entitlement: $this->createStub(ComplianceExportEntitlementPort::class),
      renderer: $renderer,
      contextBuilder: new SafetyRegisterContextBuilder(),
      eventDispatcher: $this->createStub(EventDispatcherPort::class),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Authentication required.');

    $controller->__invoke($this->request());
  }

  #[Test]
  public function testItRefusesARequestWithoutAnOrganizationRouteAttribute(): void
  {
    $renderer = $this->createMock(SafetyRegisterPdfRendererPort::class);
    $renderer->expects(self::never())->method('render');

    $this->expectException(AccessDeniedHttpException::class);

    $this->createController($this->createStub(QueryBusPort::class), renderer: $renderer)->__invoke(new Request());
  }

  #[Test]
  public function testItRefusesACallerWithoutTheExportPermission(): void
  {
    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('assertGrantedPermissions')
      ->with(self::USER_ID, self::ORGANIZATION_ID, ['organization.compliance.export'])
      ->willThrowException(OrganizationAccessDeniedException::missingPermission('organization.compliance.export'));

    $renderer = $this->createMock(SafetyRegisterPdfRendererPort::class);
    $renderer->expects(self::never())->method('render');

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Missing organization.compliance.export permission.');

    $this->createController($this->createStub(QueryBusPort::class), authorization: $authorization, renderer: $renderer)
      ->__invoke($this->request());
  }

  #[Test]
  public function testItRefusesAnOrganizationWhosePlanDoesNotIncludeTheExport(): void
  {
    $entitlement = $this->createStub(ComplianceExportEntitlementPort::class);
    $entitlement->method('isExportEntitled')->willReturn(false);

    $renderer = $this->createMock(SafetyRegisterPdfRendererPort::class);
    $renderer->expects(self::never())->method('render');

    $this->expectException(AccessDeniedHttpException::class);

    $this->createController($this->createStub(QueryBusPort::class), entitlement: $entitlement, renderer: $renderer)
      ->__invoke($this->request());
  }

  #[Test]
  public function testItRethrowsAnUnrecognisedQueryFailure(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(new RuntimeException('database is down'));

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('database is down');

    $this->createController($queryBus)->__invoke($this->request());
  }

  #[Test]
  public function testItMapsAFacilityScopedQueryFailure(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(new RuntimeException('facility read model unavailable'));

    $renderer = $this->createMock(SafetyRegisterPdfRendererPort::class);
    $renderer->expects(self::never())->method('render');

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('facility read model unavailable');

    $this->createController($queryBus, renderer: $renderer)->__invoke($this->request(self::FACILITY_ID));
  }

  private function request(?string $facilityId = null): Request
  {
    $request = new Request();
    $request->attributes->set('organizationId', self::ORGANIZATION_ID);
    if (null !== $facilityId) {
      $request->attributes->set('facilityId', $facilityId);
    }

    return $request;
  }

  private function createController(
    QueryBusPort $queryBus,
    ?OrganizationAuthorizationPort $authorization = null,
    ?ComplianceExportEntitlementPort $entitlement = null,
    ?SafetyRegisterPdfRendererPort $renderer = null,
    ?EventDispatcherPort $eventDispatcher = null,
  ): ExportSafetyRegisterController {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(new SecurityUser(
      id: self::USER_ID,
      email: 'manager@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    ));

    if (null === $entitlement) {
      $entitlement = $this->createStub(ComplianceExportEntitlementPort::class);
      $entitlement->method('isExportEntitled')->willReturn(true);
      $entitlement->method('resolvePlanKey')->willReturn('pro');
    }

    if (null === $renderer) {
      $renderer = $this->createStub(SafetyRegisterPdfRendererPort::class);
      $renderer->method('render')->willReturn('%PDF-fake');
    }

    return new ExportSafetyRegisterController(
      queryBus: $queryBus,
      authorization: $authorization ?? $this->createStub(OrganizationAuthorizationPort::class),
      branding: $this->brandingPort(),
      entitlement: $entitlement,
      renderer: $renderer,
      contextBuilder: new SafetyRegisterContextBuilder(),
      eventDispatcher: $eventDispatcher ?? $this->createStub(EventDispatcherPort::class),
      security: $security,
    );
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

  private function overviewResult(): GetComplianceOverviewResult
  {
    return new GetComplianceOverviewResult(
      generatedAt: '2026-06-01T08:00:00+00:00',
      organizationStatus: ComplianceStatus::COMPLIANT,
      totals: [
        'totalEquipmentCount' => 4,
        'activeEquipmentCount' => 4,
        'upToDateEquipmentCount' => 4,
        'dueSoonEquipmentCount' => 0,
        'overdueEquipmentCount' => 0,
        'unscheduledEquipmentCount' => 0,
        'trackedEquipmentCount' => 4,
        'complianceRate' => 100.0,
        'openLowNonConformityCount' => 0,
        'openMediumNonConformityCount' => 0,
        'openHighNonConformityCount' => 0,
        'openCriticalNonConformityCount' => 0,
      ],
      facilities: [$this->facilityView()],
    );
  }

  private function facilityComplianceResult(): GetFacilityComplianceResult
  {
    return new GetFacilityComplianceResult(
      generatedAt: '2026-06-01T08:00:00+00:00',
      facility: $this->facilityView(),
    );
  }

  private function facilityView(): FacilityComplianceView
  {
    return new FacilityComplianceView(
      facilityId: self::FACILITY_ID,
      name: 'Bâtiment A',
      type: 'building',
      parentFacilityId: null,
      path: 'Bâtiment A',
      status: ComplianceStatus::COMPLIANT,
      totalEquipmentCount: 4,
      activeEquipmentCount: 4,
      upToDateEquipmentCount: 4,
      dueSoonEquipmentCount: 0,
      overdueEquipmentCount: 0,
      unscheduledEquipmentCount: 0,
      openLowNonConformityCount: 0,
      openMediumNonConformityCount: 0,
      openHighNonConformityCount: 0,
      openCriticalNonConformityCount: 0,
      lastInspectionAt: '2026-05-20T10:00:00+00:00',
    );
  }
}
