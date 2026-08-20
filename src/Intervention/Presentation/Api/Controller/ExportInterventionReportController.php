<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Controller;

use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Intervention\Application\Contract\Resource\InterventionIssue;
use Intervention\Application\Contract\Workflow\InterventionWorkflowView;
use Intervention\Application\Port\Outbound\{InterventionMemberNamingPort, InterventionReportPdfRendererPort, InterventionSiteNamingPort};
use Intervention\Application\UseCase\Query\Activity\ListInterventionActivities\{ListInterventionActivitiesQuery, ListInterventionActivitiesResult};
use Intervention\Application\UseCase\Query\Attachment\ListInterventionAttachments\{ListInterventionAttachmentsQuery, ListInterventionAttachmentsResult};
use Intervention\Application\UseCase\Query\Workflow\GetInterventionWorkflow\{GetInterventionWorkflowQuery, GetInterventionWorkflowResult};
use Intervention\Application\UseCase\Query\Workflow\ListInterventionIssues\{ListInterventionIssuesQuery, ListInterventionIssuesResult};
use Intervention\Application\UseCase\Query\Workflow\ListInterventionWorkflow\{ListInterventionWorkflowQuery, ListInterventionWorkflowResult};
use Intervention\Domain\Event\InterventionReportExportedEvent;
use Intervention\Presentation\Api\Trait\InterventionWorkflowExceptionMapperTrait;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Application\Port\Outbound\EventDispatcherPort;
use Shared\Presentation\Api\Http\ResourceIriParser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, NotFoundHttpException};
use Throwable;

use function array_filter;
use function array_map;
use function array_unique;
use function array_values;
use function is_int;
use function is_string;
use function sprintf;

/**
 * Controller ExportInterventionReportController.
 *
 * Invokable API Platform controller (wired via `controller:` on a `Get`
 * operation, `read`/`write`/`deserialize`/`serialize`/`output` disabled —
 * mirrors `Compliance\...\ExportSafetyRegisterController`) generating a PDF
 * report of one intervention. Thin by design: authenticate, dispatch the
 * SAME read queries the workflow/attachments/activities endpoints already
 * use (no new handler — `GetInterventionWorkflowQuery`'s handler is the
 * SOLE authorization decision point: it enforces
 * `organization.interventions.read` with the module's standard 403/404
 * split), translate the results into a Twig context (IRI → display name,
 * never a business decision), render, and emit the audit event. Available
 * whenever the caller can read the intervention — no phase gate, mirroring
 * the attachment-download precedent (`DownloadInterventionAttachmentController`)
 * documented in `src/Intervention/MODULE.md`.
 *
 * The `change` resource list reuses `ListInterventionWorkflowQuery` exactly
 * as `GET /intervention-changes` already does — not a new query, just the
 * same generic workflow-listing query parameterized for a different
 * resource, needed for the applied/proposed changes summary.
 *
 * @category Controller
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ExportInterventionReportController extends AbstractController
{
  use InterventionWorkflowExceptionMapperTrait;

  // #region Constants
  private const int MAX_ITEMS_PER_PAGE = 100;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus
   * @param InterventionReportPdfRendererPort $renderer the PDF renderer port
   * @param InterventionMemberNamingPort $memberNaming the organization member naming port
   * @param InterventionSiteNamingPort $siteNaming the facility (site) naming port
   * @param EventDispatcherPort $eventDispatcher the domain event dispatcher
   * @param Security $security the security service
   */
  public function __construct(
    private readonly QueryBusPort $queryBus,
    private readonly InterventionReportPdfRendererPort $renderer,
    private readonly InterventionMemberNamingPort $memberNaming,
    private readonly InterventionSiteNamingPort $siteNaming,
    private readonly EventDispatcherPort $eventDispatcher,
    private readonly Security $security,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param Request $request the incoming HTTP request
   *
   * @return Response the streamed PDF response
   */
  public function __invoke(Request $request): Response
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $id = $request->attributes->get('id');
    if (!is_string($id) || '' === $id) {
      throw new NotFoundHttpException('Intervention not found.');
    }

    try {
      /** @var GetInterventionWorkflowResult $interventionResult */
      $interventionResult = $this->queryBus->ask(new GetInterventionWorkflowQuery(
        userId: $user->getId(),
        resource: 'intervention',
        id: $id,
      ));

      $organizationId = $interventionResult->view->organizationId;

      /** @var ListInterventionWorkflowResult $workItemsResult */
      $workItemsResult = $this->queryBus->ask(new ListInterventionWorkflowQuery(
        userId: $user->getId(),
        resource: 'work_item',
        scopeId: $id,
        filters: [],
        page: 1,
        itemsPerPage: self::MAX_ITEMS_PER_PAGE,
      ));

      /** @var ListInterventionWorkflowResult $changesResult */
      $changesResult = $this->queryBus->ask(new ListInterventionWorkflowQuery(
        userId: $user->getId(),
        resource: 'change',
        scopeId: $id,
        filters: [],
        page: 1,
        itemsPerPage: self::MAX_ITEMS_PER_PAGE,
      ));

      /** @var ListInterventionIssuesResult $issuesResult */
      $issuesResult = $this->queryBus->ask(new ListInterventionIssuesQuery(
        userId: $user->getId(),
        interventionId: $id,
      ));

      /** @var ListInterventionAttachmentsResult $attachmentsResult */
      $attachmentsResult = $this->queryBus->ask(new ListInterventionAttachmentsQuery(
        userId: $user->getId(),
        interventionId: $id,
      ));

      /** @var ListInterventionActivitiesResult $activitiesResult */
      $activitiesResult = $this->queryBus->ask(new ListInterventionActivitiesQuery(
        userId: $user->getId(),
        interventionId: $id,
        page: 1,
        itemsPerPage: self::MAX_ITEMS_PER_PAGE,
      ));
    } catch (Throwable $exception) {
      throw $this->mapWorkflowException($exception);
    }

    $context = $this->buildContext(
      organizationId: $organizationId,
      intervention: $interventionResult->view,
      workItems: $workItemsResult->page->items,
      changes: $changesResult->page->items,
      issues: $issuesResult->issues,
      attachments: $attachmentsResult->attachments,
      activities: $activitiesResult->page->items,
    );

    $pdf = $this->renderer->render($context);

    $this->eventDispatcher->dispatch(new InterventionReportExportedEvent(
      interventionId: $id,
      organizationId: $organizationId,
      actorUserId: $user->getId(),
    ));

    $reportNumber = $context['number'] ?? null;
    $fileName = sprintf('intervention-FG-%d-report.pdf', is_int($reportNumber) ? $reportNumber : 0);

    return new Response($pdf, Response::HTTP_OK, [
      'Content-Type' => 'application/pdf',
      'Content-Disposition' => sprintf('attachment; filename="%s"', $fileName),
    ]);
  }

  /**
   * Method buildContext.
   *
   * Translates the query results into a flat Twig context: IRIs are resolved
   * to display names through the naming ports, counts are tallied, and
   * nothing here decides anything the handlers have not already decided.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   * @param InterventionWorkflowView $intervention the intervention view
   * @param list<InterventionWorkflowView> $workItems the intervention's work items
   * @param list<InterventionWorkflowView> $changes the intervention's proposed/applied/rejected changes
   * @param list<InterventionIssue> $issues the intervention's computed validation issues
   * @param list<array{id: string, fileName: string, mimeType: string, size: int, label: ?string, uploadedAt: string, workItemId: ?string, kind: string}> $attachments the intervention's attachments
   * @param list<InterventionWorkflowView> $activities the intervention's activity feed, oldest first
   *
   * @return array<string, mixed> the Twig template context
   */
  private function buildContext(
    string $organizationId,
    InterventionWorkflowView $intervention,
    array $workItems,
    array $changes,
    array $issues,
    array $attachments,
    array $activities,
  ): array {
    $data = $intervention->data;

    $responsibleIri = $data['responsible'] ?? null;
    $responsibleIri = is_string($responsibleIri) ? $responsibleIri : null;

    /** @var list<string> $participantIris */
    $participantIris = array_values(array_filter(
      (array) ($data['participants'] ?? []),
      static fn (mixed $participantIri): bool => is_string($participantIri),
    ));

    $memberIds = [];
    if (null !== $responsibleIri) {
      $memberIds[] = ResourceIriParser::memberId($responsibleIri);
    }
    foreach ($participantIris as $participantIri) {
      $memberIds[] = ResourceIriParser::memberId($participantIri);
    }
    foreach ($workItems as $workItem) {
      $assigneeIri = $workItem->data['assignee'] ?? null;
      if (is_string($assigneeIri)) {
        $memberIds[] = ResourceIriParser::memberId($assigneeIri);
      }
    }
    foreach ($activities as $activity) {
      $actorIri = $activity->data['actor'] ?? null;
      if (is_string($actorIri)) {
        $memberIds[] = ResourceIriParser::memberId($actorIri);
      }
    }
    $memberNames = $this->memberNaming->displayNamesFor($organizationId, array_values(array_unique($memberIds)));

    $siteIri = $data['site'] ?? null;
    $siteName = null;
    if (is_string($siteIri)) {
      $siteId = ResourceIriParser::id($siteIri, 'facilities');
      $siteName = $this->siteNaming->findNamesByIds($organizationId, [$siteId])[$siteId] ?? null;
    }

    $responsibleId = null !== $responsibleIri ? ResourceIriParser::memberId($responsibleIri) : null;
    $participantNames = array_values(array_filter(array_map(
      static fn (string $participantIri): ?string => $memberNames[ResourceIriParser::memberId($participantIri)] ?? null,
      $participantIris,
    )));

    $changesByStatus = ['proposed' => 0, 'applied' => 0, 'rejected' => 0];
    foreach ($changes as $change) {
      $status = $change->data['status'];
      $status = is_string($status) ? $status : 'proposed';
      $changesByStatus[$status] = ($changesByStatus[$status] ?? 0) + 1;
    }

    $numberValue = $data['number'] ?? null;

    return [
      'number' => is_int($numberValue) ? $numberValue : 0,
      'name' => $data['name'],
      'type' => $data['type'],
      'status' => $data['status'],
      'priority' => $data['priority'],
      'siteName' => $siteName,
      'responsibleName' => null !== $responsibleId ? ($memberNames[$responsibleId] ?? null) : null,
      'participantNames' => $participantNames,
      'plannedStartAt' => $data['plannedStartAt'],
      'dueAt' => $data['dueAt'],
      'reviewNote' => $data['reviewNote'],
      'hasSignature' => $data['hasSignature'],
      'labels' => $data['labels'],
      'generatedAt' => new DateTimeImmutable()->format('c'),
      'workItems' => array_map(function (InterventionWorkflowView $workItem) use ($memberNames): array {
        $assigneeIri = $workItem->data['assignee'] ?? null;
        $assigneeId = is_string($assigneeIri) ? ResourceIriParser::memberId($assigneeIri) : null;

        return [
          'action' => $workItem->data['action'],
          'target' => $workItem->data['target'],
          'assigneeName' => null !== $assigneeId ? ($memberNames[$assigneeId] ?? null) : null,
          'status' => $workItem->data['status'],
          'required' => $workItem->data['required'],
          'skipReason' => $workItem->data['skipReason'],
          'evidenceCount' => $workItem->data['evidenceCount'],
        ];
      }, $workItems),
      'issues' => array_map(static fn (InterventionIssue $issue): array => [
        'severity' => $issue->severity,
        'message' => $issue->message,
      ], $issues),
      'proposedChangesCount' => $changesByStatus['proposed'],
      'appliedChangesCount' => $changesByStatus['applied'],
      'rejectedChangesCount' => $changesByStatus['rejected'],
      'attachments' => array_map(static fn (array $attachment): array => [
        'fileName' => $attachment['fileName'],
        'kind' => $attachment['kind'],
      ], $attachments),
      'activities' => array_map(function (InterventionWorkflowView $activity) use ($memberNames): array {
        $actorIri = $activity->data['actor'] ?? null;
        $actorId = is_string($actorIri) ? ResourceIriParser::memberId($actorIri) : null;

        return [
          'createdAt' => $activity->data['createdAt'],
          'kind' => $activity->data['kind'],
          'event' => $activity->data['event'],
          'actorName' => null !== $actorId ? ($memberNames[$actorId] ?? null) : null,
          'body' => $activity->data['body'],
        ];
      }, $activities),
    ];
  }
  // #endregion
}
