<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Presentation\Api\Controller;

use Auth\Infrastructure\Security\User\SecurityUser;
use Intervention\Application\Contract\Resource\InterventionIssue;
use Intervention\Application\Contract\Workflow\{InterventionWorkflowPage, InterventionWorkflowView};
use Intervention\Application\Port\Outbound\{InterventionMemberNamingPort, InterventionReportPdfRendererPort, InterventionSiteNamingPort};
use Intervention\Application\UseCase\Query\Activity\ListInterventionActivities\{ListInterventionActivitiesQuery, ListInterventionActivitiesResult};
use Intervention\Application\UseCase\Query\Attachment\ListInterventionAttachments\{ListInterventionAttachmentsQuery, ListInterventionAttachmentsResult};
use Intervention\Application\UseCase\Query\Workflow\GetInterventionWorkflow\{GetInterventionWorkflowQuery, GetInterventionWorkflowResult};
use Intervention\Application\UseCase\Query\Workflow\ListInterventionIssues\{ListInterventionIssuesQuery, ListInterventionIssuesResult};
use Intervention\Application\UseCase\Query\Workflow\ListInterventionWorkflow\{ListInterventionWorkflowQuery, ListInterventionWorkflowResult};
use Intervention\Domain\Event\InterventionReportExportedEvent;
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionNotFoundException};
use Intervention\Presentation\Api\Controller\ExportInterventionReportController;
use LogicException;
use Organization\Application\Contract\Document\OrganizationDocumentBranding;
use Organization\Application\Port\Inbound\OrganizationDocumentBrandingPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Application\Port\Outbound\EventDispatcherPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, NotFoundHttpException};

/**
 * Test ExportInterventionReportControllerTest.
 *
 * The controller is thin by design: `GetInterventionWorkflowQuery`'s handler
 * is the sole authorization decision point (403/404 split), so this suite
 * proves the controller propagates that decision faithfully rather than
 * re-deciding it, then proves what it does own — the Twig context assembly
 * (IRI-to-name resolution, counts) and its localization (branding, language,
 * reformatted dates), the audit event, and the PDF response shape. Mirrors
 * `Compliance\...\ExportSafetyRegisterControllerTest`.
 *
 * @category Controller Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ExportInterventionReportController::class)]
final class ExportInterventionReportControllerTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655507101';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655507102';

  private const string INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655507103';

  private const string SITE_ID = '550e8400-e29b-41d4-a716-446655507104';

  private const string RESPONSIBLE_ID = '550e8400-e29b-41d4-a716-446655507105';

  private const string PARTICIPANT_ID = '550e8400-e29b-41d4-a716-446655507106';

  #[Test]
  public function testItReturnsThePdfAttachmentWithTheReportNumberInTheFilename(): void
  {
    $response = $this->createController($this->queryBus())->__invoke($this->request());

    self::assertInstanceOf(Response::class, $response);
    self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    self::assertSame('application/pdf', $response->headers->get('Content-Type'));
    self::assertSame('%PDF-fake', $response->getContent());
    self::assertSame(
      'attachment; filename="intervention-FG-42-report.pdf"',
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
    self::assertMatchesRegularExpression('/^\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}$/', (string) $context['generatedAtFormatted']);
    self::assertSame('01/06/2026 10:00', $context['plannedStartAt']);
    self::assertSame('10/06/2026 19:00', $context['dueAt']);

    $activities = $context['activities'];
    self::assertIsArray($activities);
    $firstActivity = $activities[0];
    self::assertIsArray($firstActivity);
    self::assertSame('02/06/2026 11:15', $firstActivity['createdAt']);
  }

  #[Test]
  public function testItResolvesSiteAndMemberNamesAndTalliesChangeCounts(): void
  {
    $context = $this->renderedContext($this->queryBus());

    self::assertSame('Main Building', $context['siteName']);
    self::assertSame('Jane Doe', $context['responsibleName']);
    self::assertSame(['John Roe'], $context['participantNames']);
    self::assertSame(1, $context['proposedChangesCount']);
    self::assertSame(1, $context['appliedChangesCount']);
    self::assertSame(0, $context['rejectedChangesCount']);
  }

  #[Test]
  public function testItDegradesGracefullyWhenBrandingIsMinimalAndTimezoneIsUnrecognised(): void
  {
    $branding = $this->createStub(OrganizationDocumentBrandingPort::class);
    $branding->method('getDocumentBranding')->willReturn(new OrganizationDocumentBranding(
      organizationName: 'Acme',
      logoDataUri: null,
      legalName: null,
      registrationNumber: null,
      vatNumber: null,
      timezone: 'Not/ARealZone',
      locale: 'en',
      dateFormat: 'unknown-format',
    ));

    $context = $this->renderedContext($this->queryBus(), branding: $branding);

    self::assertSame([
      'name' => 'Acme',
      'logoDataUri' => null,
      'legalName' => null,
      'registrationNumber' => null,
      'vatNumber' => null,
    ], $context['org']);
    self::assertSame('en', $context['lang']);
    self::assertSame('2026-06-01 08:00', $context['plannedStartAt']);
    self::assertSame('2026-06-10 17:00', $context['dueAt']);

    $activities = $context['activities'];
    self::assertIsArray($activities);
    $firstActivity = $activities[0];
    self::assertIsArray($firstActivity);
    self::assertSame('2026-06-02 09:15', $firstActivity['createdAt']);
  }

  #[Test]
  public function testItAuditsTheExportWithTheOrganizationAndActor(): void
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

    self::assertInstanceOf(InterventionReportExportedEvent::class, $dispatched);
    self::assertSame(self::INTERVENTION_ID, $dispatched->interventionId);
    self::assertSame(self::ORGANIZATION_ID, $dispatched->organizationId);
    self::assertSame(self::USER_ID, $dispatched->actorUserId);
  }

  #[Test]
  public function testItDoesNotAuditOrRenderWhenTheWorkflowQueryFails(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(InterventionNotFoundException::withId(self::INTERVENTION_ID));

    $renderer = $this->createMock(InterventionReportPdfRendererPort::class);
    $renderer->expects(self::never())->method('render');

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $this->expectException(NotFoundHttpException::class);

    $this->createController($queryBus, renderer: $renderer, eventDispatcher: $eventDispatcher)
      ->__invoke($this->request());
  }

  #[Test]
  public function testItRefusesAnUnauthenticatedCaller(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $renderer = $this->createMock(InterventionReportPdfRendererPort::class);
    $renderer->expects(self::never())->method('render');

    $controller = new ExportInterventionReportController(
      queryBus: $this->createStub(QueryBusPort::class),
      renderer: $renderer,
      branding: $this->brandingPort(),
      memberNaming: $this->memberNamingPort(),
      siteNaming: $this->siteNamingPort(),
      eventDispatcher: $this->createStub(EventDispatcherPort::class),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Authentication required.');

    $controller->__invoke($this->request());
  }

  #[Test]
  public function testItRefusesARequestWithoutAnIdAttribute(): void
  {
    $renderer = $this->createMock(InterventionReportPdfRendererPort::class);
    $renderer->expects(self::never())->method('render');

    $this->expectException(NotFoundHttpException::class);
    $this->expectExceptionMessage('Intervention not found.');

    $this->createController($this->queryBus(), renderer: $renderer)->__invoke(new Request());
  }

  #[Test]
  public function testItMapsANotFoundQueryFailure(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(InterventionNotFoundException::withId(self::INTERVENTION_ID));

    $renderer = $this->createMock(InterventionReportPdfRendererPort::class);
    $renderer->expects(self::never())->method('render');

    $this->expectException(NotFoundHttpException::class);

    $this->createController($queryBus, renderer: $renderer)->__invoke($this->request());
  }

  #[Test]
  public function testItMapsAnAccessDeniedQueryFailure(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(new InterventionAccessDeniedException('Missing organization.interventions.read permission.'));

    $renderer = $this->createMock(InterventionReportPdfRendererPort::class);
    $renderer->expects(self::never())->method('render');

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Missing organization.interventions.read permission.');

    $this->createController($queryBus, renderer: $renderer)->__invoke($this->request());
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
    $request->attributes->set('id', self::INTERVENTION_ID);

    return $request;
  }

  private function createController(
    QueryBusPort $queryBus,
    ?OrganizationDocumentBrandingPort $branding = null,
    ?InterventionReportPdfRendererPort $renderer = null,
    ?EventDispatcherPort $eventDispatcher = null,
  ): ExportInterventionReportController {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(new SecurityUser(
      id: self::USER_ID,
      email: 'inspector@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    ));

    if (null === $renderer) {
      $renderer = $this->createStub(InterventionReportPdfRendererPort::class);
      $renderer->method('render')->willReturn('%PDF-fake');
    }

    return new ExportInterventionReportController(
      queryBus: $queryBus,
      renderer: $renderer,
      branding: $branding ?? $this->brandingPort(),
      memberNaming: $this->memberNamingPort(),
      siteNaming: $this->siteNamingPort(),
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
  private function renderedContext(QueryBusPort $queryBus, ?OrganizationDocumentBrandingPort $branding = null): array
  {
    $context = null;

    /** @var InterventionReportPdfRendererPort&MockObject $renderer */
    $renderer = $this->createMock(InterventionReportPdfRendererPort::class);
    $renderer->expects(self::once())
      ->method('render')
      ->willReturnCallback(static function (array $received) use (&$context): string {
        $context = $received;

        return '%PDF-fake';
      });

    $this->createController($queryBus, branding: $branding, renderer: $renderer)->__invoke($this->request());

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

  private function memberNamingPort(): InterventionMemberNamingPort
  {
    $port = $this->createStub(InterventionMemberNamingPort::class);
    $port->method('displayNamesFor')->willReturn([
      self::RESPONSIBLE_ID => 'Jane Doe',
      self::PARTICIPANT_ID => 'John Roe',
    ]);

    return $port;
  }

  private function siteNamingPort(): InterventionSiteNamingPort
  {
    $port = $this->createStub(InterventionSiteNamingPort::class);
    $port->method('findNamesByIds')->willReturn([self::SITE_ID => 'Main Building']);

    return $port;
  }

  private function queryBus(): QueryBusPort
  {
    $interventionResult = new GetInterventionWorkflowResult($this->interventionView());
    $workItemsResult = new ListInterventionWorkflowResult(new InterventionWorkflowPage(items: [], page: 1, itemsPerPage: 100, total: 0));
    $changesResult = new ListInterventionWorkflowResult(new InterventionWorkflowPage(
      items: [$this->changeView('proposed'), $this->changeView('applied')],
      page: 1,
      itemsPerPage: 100,
      total: 2,
    ));
    $issuesResult = new ListInterventionIssuesResult([
      new InterventionIssue(severity: 'warning', resourceType: 'work_item', resourceId: 'wi-1', field: null, message: 'Missing evidence.'),
    ]);
    $attachmentsResult = new ListInterventionAttachmentsResult([
      ['id' => 'att-1', 'fileName' => 'photo.jpg', 'mimeType' => 'image/jpeg', 'size' => 1024, 'label' => null, 'uploadedAt' => '2026-06-01T09:00:00+00:00', 'workItemId' => null, 'kind' => 'photo'],
    ]);
    $activitiesResult = new ListInterventionActivitiesResult(new InterventionWorkflowPage(
      items: [$this->activityView()],
      page: 1,
      itemsPerPage: 100,
      total: 1,
    ));

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturnCallback(
      static function (object $query) use ($interventionResult, $workItemsResult, $changesResult, $issuesResult, $attachmentsResult, $activitiesResult): object {
        return match (true) {
          $query instanceof GetInterventionWorkflowQuery => $interventionResult,
          $query instanceof ListInterventionIssuesQuery => $issuesResult,
          $query instanceof ListInterventionAttachmentsQuery => $attachmentsResult,
          $query instanceof ListInterventionActivitiesQuery => $activitiesResult,
          $query instanceof ListInterventionWorkflowQuery && 'change' === $query->resource => $changesResult,
          $query instanceof ListInterventionWorkflowQuery => $workItemsResult,
          default => throw new LogicException('Unexpected query: ' . $query::class),
        };
      },
    );

    return $queryBus;
  }

  private function interventionView(): InterventionWorkflowView
  {
    return new InterventionWorkflowView(
      resource: 'intervention',
      organizationId: self::ORGANIZATION_ID,
      data: [
        'number' => 42,
        'name' => 'Annual fire drill',
        'type' => 'inspection',
        'status' => 'completed',
        'priority' => 'high',
        'site' => '/api/facilities/' . self::SITE_ID,
        'responsible' => '/api/organizations/' . self::ORGANIZATION_ID . '/members/' . self::RESPONSIBLE_ID,
        'participants' => ['/api/organizations/' . self::ORGANIZATION_ID . '/members/' . self::PARTICIPANT_ID],
        'plannedStartAt' => '2026-06-01T08:00:00+00:00',
        'dueAt' => '2026-06-10T17:00:00+00:00',
        'reviewNote' => null,
        'hasSignature' => false,
        'labels' => [],
      ],
    );
  }

  private function changeView(string $status): InterventionWorkflowView
  {
    return new InterventionWorkflowView(
      resource: 'change',
      organizationId: self::ORGANIZATION_ID,
      data: ['status' => $status],
    );
  }

  private function activityView(): InterventionWorkflowView
  {
    return new InterventionWorkflowView(
      resource: 'activity',
      organizationId: self::ORGANIZATION_ID,
      data: [
        'createdAt' => '2026-06-02T09:15:00+00:00',
        'kind' => 'comment',
        'event' => 'note_added',
        'actor' => '/api/organizations/' . self::ORGANIZATION_ID . '/members/' . self::RESPONSIBLE_ID,
        'body' => 'Checked extinguishers.',
      ],
    );
  }
  // #endregion
}
