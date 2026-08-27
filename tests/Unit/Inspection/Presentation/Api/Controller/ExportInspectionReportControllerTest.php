<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Presentation\Api\Controller;

use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Inspection\Application\Contract\Response\InspectionResponseView;
use Inspection\Application\Port\Outbound\{InspectionReportEntitlementPort, InspectionReportPdfRendererPort};
use Inspection\Application\UseCase\Query\Inspection\GetInspection\{GetInspectionQuery, GetInspectionResult};
use Inspection\Application\UseCase\Query\NonConformity\ListNonConformities\{ListNonConformitiesQuery, NonConformityResult};
use Inspection\Application\UseCase\Query\Response\ListInspectionResponses\{ListInspectionResponsesQuery, ListInspectionResponsesResult};
use Inspection\Domain\Event\Export\InspectionReportExportedEvent;
use Inspection\Domain\Exception\{InspectionAccessDeniedException, InspectionNotFoundException};
use Inspection\Presentation\Api\Controller\ExportInspectionReportController;
use LogicException;
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Contract\Document\OrganizationDocumentBranding;
use Organization\Application\Port\Inbound\{OrganizationAuthorizationPort, OrganizationDocumentBrandingPort};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Contract\Pagination\PaginatedResult;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Application\Port\Outbound\EventDispatcherPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};

use function md5;
use function substr;

/**
 * Test ExportInspectionReportControllerTest.
 *
 * The controller is thin by design: authorization is the module's standard
 * `resolveAccess` 403/404 split, the entitlement gate mirrors the safety
 * register's `pro`/`max` decision, and the read queries are the SAME ones
 * the JSON endpoints already dispatch. This suite proves the controller
 * propagates those decisions faithfully, then proves what it does own — the
 * Twig context assembly (checklist answers stringified, non-conformity
 * rows) and its localization, the audit event, and the PDF response shape.
 * Mirrors `Intervention\...\ExportInterventionReportControllerTest`.
 *
 * @category Controller Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ExportInspectionReportController::class)]
final class ExportInspectionReportControllerTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655509101';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655509102';

  private const string INSPECTION_ID = '550e8400-e29b-41d4-a716-446655509103';

  #[Test]
  public function testItReturnsThePdfAttachmentWithTheInspectionIdInTheFilename(): void
  {
    $response = $this->createController($this->queryBus())->__invoke($this->request());

    self::assertInstanceOf(Response::class, $response);
    self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    self::assertSame('application/pdf', $response->headers->get('Content-Type'));
    self::assertSame('%PDF-fake', $response->getContent());
    self::assertSame(
      'attachment; filename="inspection-' . self::INSPECTION_ID . '-report.pdf"',
      $response->headers->get('Content-Disposition'),
    );
  }

  #[Test]
  public function testItLocalizesTheContextWithBrandingLanguageAndFormattedDates(): void
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
    self::assertSame('01/06/2026 10:00', $context['performedAt']);

    $nonConformities = $context['nonConformities'];
    self::assertIsArray($nonConformities);
    $first = $nonConformities[0];
    self::assertIsArray($first);
    self::assertSame('10/06/2026', $first['dueAt']);
    self::assertNull($first['resolvedAt']);
    self::assertSame('01/06/2026 12:00', $first['createdAt']);
  }

  #[Test]
  public function testItShapesTheIdentityResponsesAndNonConformitySections(): void
  {
    $context = $this->renderedContext($this->queryBus());

    self::assertSame('closed', $context['status']);
    self::assertSame('passed', $context['result']);
    self::assertSame('Jane Doe', $context['inspectorName']);
    self::assertSame('Main Building', $context['facilityName']);
    self::assertSame('SN-0042', $context['equipmentSerialNumber']);
    self::assertSame('Quarterly extinguisher checklist', $context['checklistName']);
    self::assertTrue($context['hasSignature']);
    self::assertSame(1, $context['nonConformitiesCount']);

    self::assertSame([
      ['itemKey' => 'pressure_ok', 'value' => 'yes'],
      ['itemKey' => 'hose_state', 'value' => 'worn'],
      ['itemKey' => 'checked_points', 'value' => 'nozzle, pin'],
    ], $context['responses']);

    $nonConformities = $context['nonConformities'];
    self::assertIsArray($nonConformities);
    $first = $nonConformities[0];
    self::assertIsArray($first);
    self::assertSame('high', $first['severity']);
    self::assertSame('open', $first['status']);
    self::assertSame('Hose is worn out.', $first['description']);
  }

  #[Test]
  public function testItAuditsTheExportWithThePlanKeyAndActor(): void
  {
    $dispatched = null;

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->willReturnCallback(static function (object $event) use (&$dispatched): void {
        $dispatched = $event;
      });

    $this->createController($this->queryBus(), eventDispatcher: $eventDispatcher)->__invoke($this->request());

    self::assertInstanceOf(InspectionReportExportedEvent::class, $dispatched);
    self::assertSame(self::INSPECTION_ID, $dispatched->inspectionId);
    self::assertSame(self::ORGANIZATION_ID, $dispatched->organizationId);
    self::assertSame(self::USER_ID, $dispatched->actorUserId);
    self::assertSame('pro', $dispatched->planKey);
  }

  #[Test]
  public function testItRefusesAnUnauthenticatedCaller(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $renderer = $this->createMock(InspectionReportPdfRendererPort::class);
    $renderer->expects(self::never())->method('render');

    $controller = $this->createController($this->queryBus(), renderer: $renderer, security: $security);

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Authentication required.');

    $controller->__invoke($this->request());
  }

  #[Test]
  public function testItRefusesARequestWithoutUriParameters(): void
  {
    $this->expectException(BadRequestHttpException::class);

    $this->createController($this->queryBus())->__invoke(new Request());
  }

  #[Test]
  public function testItAnswers404ForACallerOutsideTheOrganizationScope(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::OUTSIDE_SCOPE);

    $renderer = $this->createMock(InspectionReportPdfRendererPort::class);
    $renderer->expects(self::never())->method('render');

    $controller = $this->createController($this->queryBus(), renderer: $renderer, authorization: $authorization);

    $this->expectException(NotFoundHttpException::class);
    $this->expectExceptionMessage('Organization not found.');

    $controller->__invoke($this->request());
  }

  #[Test]
  public function testItAnswers403ForAMemberWithoutTheReadPermission(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::MISSING_PERMISSION);

    $controller = $this->createController($this->queryBus(), authorization: $authorization);

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Missing organization.inspection.read permission.');

    $controller->__invoke($this->request());
  }

  #[Test]
  public function testItAnswers403WhenThePlanIsNotEntitled(): void
  {
    $entitlement = $this->createStub(InspectionReportEntitlementPort::class);
    $entitlement->method('isExportEntitled')->willReturn(false);

    $renderer = $this->createMock(InspectionReportPdfRendererPort::class);
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
  public function testItMapsANotFoundQueryFailureAndDoesNotAudit(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(InspectionNotFoundException::withId(self::INSPECTION_ID));

    $renderer = $this->createMock(InspectionReportPdfRendererPort::class);
    $renderer->expects(self::never())->method('render');

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $controller = $this->createController($queryBus, renderer: $renderer, eventDispatcher: $eventDispatcher);

    $this->expectException(NotFoundHttpException::class);

    $controller->__invoke($this->request());
  }

  #[Test]
  public function testItMapsAnAccessDeniedQueryFailure(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(new InspectionAccessDeniedException('Missing organization.inspection.read permission.'));

    $this->expectException(AccessDeniedHttpException::class);

    $this->createController($queryBus)->__invoke($this->request());
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
    $request->attributes->set('inspectionId', self::INSPECTION_ID);

    return $request;
  }

  private function createController(
    QueryBusPort $queryBus,
    ?InspectionReportPdfRendererPort $renderer = null,
    ?OrganizationAuthorizationPort $authorization = null,
    ?InspectionReportEntitlementPort $entitlement = null,
    ?EventDispatcherPort $eventDispatcher = null,
    ?Security $security = null,
  ): ExportInspectionReportController {
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
      $renderer = $this->createStub(InspectionReportPdfRendererPort::class);
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

    return new ExportInspectionReportController(
      queryBus: $queryBus,
      authorization: $authorization,
      branding: $this->brandingPort(),
      entitlement: $entitlement,
      renderer: $renderer,
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

    /** @var InspectionReportPdfRendererPort&MockObject $renderer */
    $renderer = $this->createMock(InspectionReportPdfRendererPort::class);
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
    $inspectionResult = new GetInspectionResult(
      inspectionId: self::INSPECTION_ID,
      organizationId: self::ORGANIZATION_ID,
      equipmentId: '550e8400-e29b-41d4-a716-446655509104',
      facilityId: '550e8400-e29b-41d4-a716-446655509105',
      result: 'passed',
      status: 'closed',
      performedAt: '2026-06-01T08:00:00+00:00',
      inspectorType: 'internal',
      inspectorName: 'Jane Doe',
      inspectorUserId: self::USER_ID,
      inspectorOrganizationName: null,
      checklistId: '550e8400-e29b-41d4-a716-446655509106',
      notes: 'All good except the hose.',
      signature: 'data:image/png;base64,abc',
      nonConformitiesCount: 1,
      createdAt: new DateTimeImmutable('2026-06-01T08:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-06-01T09:00:00+00:00'),
      equipmentSerialNumber: 'SN-0042',
      facilityName: 'Main Building',
      checklistName: 'Quarterly extinguisher checklist',
    );

    $responsesResult = new ListInspectionResponsesResult(
      views: [
        $this->responseView('pressure_ok', true),
        $this->responseView('hose_state', 'worn'),
        $this->responseView('checked_points', ['nozzle', 'pin']),
      ],
      page: 1,
      itemsPerPage: 200,
      total: 3,
    );

    $nonConformitiesResult = new PaginatedResult(items: [
      new NonConformityResult(
        nonConformityId: '550e8400-e29b-41d4-a716-446655509107',
        inspectionId: self::INSPECTION_ID,
        description: 'Hose is worn out.',
        severity: 'high',
        status: 'open',
        dueAt: '2026-06-10T00:00:00+00:00',
        resolvedAt: null,
        notes: null,
        createdAt: new DateTimeImmutable('2026-06-01T10:00:00+00:00'),
        updatedAt: new DateTimeImmutable('2026-06-01T10:00:00+00:00'),
      ),
    ], total: 1, limit: 200, offset: 0);

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturnCallback(
      static function (object $query) use ($inspectionResult, $responsesResult, $nonConformitiesResult): object {
        return match (true) {
          $query instanceof GetInspectionQuery => $inspectionResult,
          $query instanceof ListInspectionResponsesQuery => $responsesResult,
          $query instanceof ListNonConformitiesQuery => $nonConformitiesResult,
          default => throw new LogicException('Unexpected query: ' . $query::class),
        };
      },
    );

    return $queryBus;
  }

  private function responseView(string $itemKey, mixed $value): InspectionResponseView
  {
    return new InspectionResponseView(
      id: '550e8400-e29b-41d4-a716-4466555091' . substr(md5($itemKey), 0, 2),
      organizationId: self::ORGANIZATION_ID,
      interventionId: null,
      inspectionId: self::INSPECTION_ID,
      clientId: null,
      recordStatus: 'published',
      revision: 1,
      itemKey: $itemKey,
      value: $value,
      createdAt: new DateTimeImmutable('2026-06-01T08:30:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-06-01T08:30:00+00:00'),
    );
  }
  // #endregion
}
