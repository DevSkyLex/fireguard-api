<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Presentation\Api\Controller;

use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Equipment\Application\Port\Outbound\{EquipmentReportEntitlementPort, EquipmentReportPdfRendererPort};
use Equipment\Application\UseCase\Query\Equipment\GetEquipment\{GetEquipmentQuery, GetEquipmentResult};
use Equipment\Application\UseCase\Query\Equipment\ListEquipmentAttachments\{ListEquipmentAttachmentsQuery, ListEquipmentAttachmentsResult};
use Equipment\Application\UseCase\Query\Equipment\ListMaintenanceLogs\{ListMaintenanceLogsQuery, ListMaintenanceLogsResult};
use Equipment\Domain\Event\Export\EquipmentReportExportedEvent;
use Equipment\Domain\Exception\EquipmentNotFoundException;
use Equipment\Presentation\Api\Controller\ExportEquipmentReportController;
use LogicException;
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
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};

/**
 * Test ExportEquipmentReportControllerTest.
 *
 * The controller is thin by design: authorization is the module's standard
 * `resolveAccess` 403/404 split, the entitlement gate mirrors the safety
 * register's `pro`/`max` decision, and the read queries are the SAME ones
 * the JSON endpoints already dispatch. This suite proves the controller
 * propagates those decisions faithfully, then proves what it does own — the
 * Twig context assembly and its localization, the audit event, and the PDF
 * response shape. Mirrors `Intervention\...\ExportInterventionReportControllerTest`.
 *
 * @category Controller Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ExportEquipmentReportController::class)]
final class ExportEquipmentReportControllerTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655508101';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655508102';

  private const string EQUIPMENT_ID = '550e8400-e29b-41d4-a716-446655508103';

  #[Test]
  public function testItReturnsThePdfAttachmentWithTheEquipmentIdInTheFilename(): void
  {
    $response = $this->createController($this->queryBus())->__invoke($this->request());

    self::assertInstanceOf(Response::class, $response);
    self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    self::assertSame('application/pdf', $response->headers->get('Content-Type'));
    self::assertSame('%PDF-fake', $response->getContent());
    self::assertSame(
      'attachment; filename="equipment-' . self::EQUIPMENT_ID . '-report.pdf"',
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
    self::assertSame('01/03/2026', $context['installedAt']);
    self::assertSame('15/03/2026', $context['commissionedAt']);

    $logs = $context['maintenanceLogs'];
    self::assertIsArray($logs);
    $firstLog = $logs[0];
    self::assertIsArray($firstLog);
    self::assertSame('01/06/2026 10:00', $firstLog['startedAt']);
    self::assertSame('01/06/2026 12:30', $firstLog['completedAt']);

    $attachments = $context['attachments'];
    self::assertIsArray($attachments);
    $firstAttachment = $attachments[0];
    self::assertIsArray($firstAttachment);
    self::assertSame('02/06/2026 11:00', $firstAttachment['uploadedAt']);
  }

  #[Test]
  public function testItShapesTheIdentitySectionFromTheEquipmentResult(): void
  {
    $context = $this->renderedContext($this->queryBus());

    self::assertSame('extinguisher', $context['type']);
    self::assertSame('co2', $context['subType']);
    self::assertSame('Acme', $context['brand']);
    self::assertSame('X-200', $context['model']);
    self::assertSame('SN-0042', $context['serialNumber']);
    self::assertSame('operational', $context['status']);
    self::assertSame('Main Building', $context['facilityName']);
    self::assertSame('up_to_date', $context['maintenanceDueStatus']);
    self::assertSame(['fire', 'ground-floor'], $context['tags']);
    self::assertSame(1, $context['maintenanceLogsTotal']);
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

    self::assertInstanceOf(EquipmentReportExportedEvent::class, $dispatched);
    self::assertSame(self::EQUIPMENT_ID, $dispatched->equipmentId);
    self::assertSame(self::ORGANIZATION_ID, $dispatched->organizationId);
    self::assertSame(self::USER_ID, $dispatched->actorUserId);
    self::assertSame('pro', $dispatched->planKey);
  }

  #[Test]
  public function testItRefusesAnUnauthenticatedCaller(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $renderer = $this->createMock(EquipmentReportPdfRendererPort::class);
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

    $renderer = $this->createMock(EquipmentReportPdfRendererPort::class);
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
    $this->expectExceptionMessage('Missing organization.equipment.read permission.');

    $controller->__invoke($this->request());
  }

  #[Test]
  public function testItAnswers403WhenThePlanIsNotEntitled(): void
  {
    $entitlement = $this->createStub(EquipmentReportEntitlementPort::class);
    $entitlement->method('isExportEntitled')->willReturn(false);

    $renderer = $this->createMock(EquipmentReportPdfRendererPort::class);
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
    $this->expectExceptionMessage('plan does not include the equipment sheet PDF export');

    $controller->__invoke($this->request());
  }

  #[Test]
  public function testItMapsANotFoundQueryFailureAndDoesNotAudit(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(EquipmentNotFoundException::withId(self::EQUIPMENT_ID));

    $renderer = $this->createMock(EquipmentReportPdfRendererPort::class);
    $renderer->expects(self::never())->method('render');

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $controller = $this->createController($queryBus, renderer: $renderer, eventDispatcher: $eventDispatcher);

    $this->expectException(NotFoundHttpException::class);

    $controller->__invoke($this->request());
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
    $request->attributes->set('equipmentId', self::EQUIPMENT_ID);

    return $request;
  }

  private function createController(
    QueryBusPort $queryBus,
    ?EquipmentReportPdfRendererPort $renderer = null,
    ?OrganizationAuthorizationPort $authorization = null,
    ?EquipmentReportEntitlementPort $entitlement = null,
    ?EventDispatcherPort $eventDispatcher = null,
    ?Security $security = null,
  ): ExportEquipmentReportController {
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
      $renderer = $this->createStub(EquipmentReportPdfRendererPort::class);
      $renderer->method('render')->willReturn('%PDF-fake');
    }

    if (null === $authorization) {
      $authorization = $this->createStub(OrganizationAuthorizationPort::class);
      $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);
    }

    if (null === $entitlement) {
      $entitlement = $this->createStub(EquipmentReportEntitlementPort::class);
      $entitlement->method('isExportEntitled')->willReturn(true);
      $entitlement->method('resolvePlanKey')->willReturn('pro');
    }

    return new ExportEquipmentReportController(
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

    /** @var EquipmentReportPdfRendererPort&MockObject $renderer */
    $renderer = $this->createMock(EquipmentReportPdfRendererPort::class);
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
    $equipmentResult = new GetEquipmentResult(
      equipmentId: self::EQUIPMENT_ID,
      organizationId: self::ORGANIZATION_ID,
      facilityId: '550e8400-e29b-41d4-a716-446655508104',
      type: 'extinguisher',
      subType: 'co2',
      brand: 'Acme',
      model: 'X-200',
      serialNumber: 'SN-0042',
      locationLabel: 'Hall A',
      status: 'operational',
      installedAt: '2026-03-01T00:00:00+00:00',
      commissionedAt: '2026-03-15T00:00:00+00:00',
      tags: [
        ['id' => 'tag-1', 'name' => 'fire', 'organizationId' => self::ORGANIZATION_ID],
        ['id' => 'tag-2', 'name' => 'ground-floor', 'organizationId' => self::ORGANIZATION_ID],
      ],
      createdAt: new DateTimeImmutable('2026-03-01T00:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-06-01T00:00:00+00:00'),
      maintenanceDueStatus: 'up_to_date',
      facilityName: 'Main Building',
    );

    $logsResult = new ListMaintenanceLogsResult(
      logs: [[
        'id' => 'log-1',
        'equipmentId' => self::EQUIPMENT_ID,
        'organizationId' => self::ORGANIZATION_ID,
        'startedAt' => '2026-06-01T08:00:00+00:00',
        'completedAt' => '2026-06-01T10:30:00+00:00',
        'source' => 'intervention',
        'interventionId' => 'int-1',
        'interventionNumber' => 77,
        'workItemAction' => 'inspect',
        'actorId' => 'member-1',
        'summary' => 'Pressure checked.',
      ]],
      total: 1,
    );

    $attachmentsResult = new ListEquipmentAttachmentsResult(
      attachments: [[
        'id' => 'att-1',
        'fileName' => 'certificate.pdf',
        'mimeType' => 'application/pdf',
        'size' => 2048,
        'label' => 'Certificate',
        'uploadedAt' => '2026-06-02T09:00:00+00:00',
      ]],
    );

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturnCallback(
      static function (object $query) use ($equipmentResult, $logsResult, $attachmentsResult): object {
        return match (true) {
          $query instanceof GetEquipmentQuery => $equipmentResult,
          $query instanceof ListMaintenanceLogsQuery => $logsResult,
          $query instanceof ListEquipmentAttachmentsQuery => $attachmentsResult,
          default => throw new LogicException('Unexpected query: ' . $query::class),
        };
      },
    );

    return $queryBus;
  }
  // #endregion
}
