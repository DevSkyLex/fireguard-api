<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Command\Export\ExportInterventionReportDocument;

use DateTimeImmutable;
use Intervention\Application\Contract\Event\InterventionReportExportedEvent;
use Intervention\Application\Contract\Export\InterventionExportDocument;
use Intervention\Application\Contract\Resource\InterventionIssue;
use Intervention\Application\Contract\Workflow\InterventionWorkflowView;
use Intervention\Application\Port\Outbound\{InterventionMemberNamingPort, InterventionReportPdfRendererPort, InterventionSiteNamingPort};
use Intervention\Application\UseCase\Query\Activity\ListInterventionActivities\{ListInterventionActivitiesQuery, ListInterventionActivitiesResult};
use Intervention\Application\UseCase\Query\Attachment\ListInterventionAttachments\{ListInterventionAttachmentsQuery, ListInterventionAttachmentsResult};
use Intervention\Application\UseCase\Query\Workflow\GetInterventionWorkflow\{GetInterventionWorkflowQuery, GetInterventionWorkflowResult};
use Intervention\Application\UseCase\Query\Workflow\ListInterventionIssues\{ListInterventionIssuesQuery, ListInterventionIssuesResult};
use Intervention\Application\UseCase\Query\Workflow\ListInterventionWorkflow\{ListInterventionWorkflowQuery, ListInterventionWorkflowResult};
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionNotFoundException};
use Organization\Application\Contract\Document\OrganizationDocumentBranding;
use Organization\Application\Port\Inbound\{OrganizationAuthorizationPort, OrganizationDocumentBrandingPort};
use Shared\Application\Contract\Document\DocumentDateFormatter;
use Shared\Application\Contract\Http\ResourceIriParser;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Application\Port\Outbound\{EventDispatcherPort, TransactionManagerPort};

use function array_filter;
use function array_map;
use function array_unique;
use function array_values;
use function base64_encode;
use function is_array;
use function is_int;
use function is_string;
use function sprintf;

/** Renders the original document and records its audit intent inside the same owning main transaction. @category UseCase */
final readonly class ExportInterventionReportDocumentHandler implements CommandHandler
{
  private const int MAX_ITEMS_PER_PAGE = 100;

  public function __construct(
    private QueryBusPort $queryBus,
    private InterventionReportPdfRendererPort $renderer,
    private OrganizationDocumentBrandingPort $branding,
    private InterventionMemberNamingPort $memberNaming,
    private InterventionSiteNamingPort $siteNaming,
    private EventDispatcherPort $events,
    private TransactionManagerPort $transactions,
    private OrganizationAuthorizationPort $authorization,
  ) {
  }

  public function __invoke(ExportInterventionReportDocumentCommand $command): InterventionExportDocument
  {
    return $this->transactions->transactional(fn (): InterventionExportDocument => $this->export($command));
  }

  private function export(ExportInterventionReportDocumentCommand $command): InterventionExportDocument
  {
    $id = $command->resourceId;
    /** @var GetInterventionWorkflowResult $interventionResult */
    $interventionResult = $this->queryBus->ask(new GetInterventionWorkflowQuery(
      userId: $command->userId,
      resource: 'intervention',
      id: $id,
    ));

    $organizationId = $interventionResult->view->organizationId;
    $this->authorize($command->userId, $organizationId);

    $snapshot = $interventionResult->view->data['closureSnapshot'] ?? null;
    if (is_array($snapshot) && is_array($snapshot['report'] ?? null)) {
      /** @var array<string,mixed> $context */
      $context = $snapshot['report'];
    } else {

      /** @var ListInterventionWorkflowResult $workItemsResult */
      $workItemsResult = $this->queryBus->ask(new ListInterventionWorkflowQuery(
        userId: $command->userId,
        resource: 'work_item',
        scopeId: $id,
        filters: [],
        page: 1,
        itemsPerPage: self::MAX_ITEMS_PER_PAGE,
      ));

      /** @var ListInterventionWorkflowResult $changesResult */
      $changesResult = $this->queryBus->ask(new ListInterventionWorkflowQuery(
        userId: $command->userId,
        resource: 'change',
        scopeId: $id,
        filters: [],
        page: 1,
        itemsPerPage: self::MAX_ITEMS_PER_PAGE,
      ));

      /** @var ListInterventionIssuesResult $issuesResult */
      $issuesResult = $this->queryBus->ask(new ListInterventionIssuesQuery(
        userId: $command->userId,
        interventionId: $id,
      ));

      /** @var ListInterventionAttachmentsResult $attachmentsResult */
      $attachmentsResult = $this->queryBus->ask(new ListInterventionAttachmentsQuery(
        userId: $command->userId,
        interventionId: $id,
      ));

      /** @var ListInterventionActivitiesResult $activitiesResult */
      $activitiesResult = $this->queryBus->ask(new ListInterventionActivitiesQuery(
        userId: $command->userId,
        interventionId: $id,
        page: 1,
        itemsPerPage: self::MAX_ITEMS_PER_PAGE,
      ));
      $context = $this->buildContext(
        organizationId: $organizationId,
        intervention: $interventionResult->view,
        workItems: $workItemsResult->page->items,
        changes: $changesResult->page->items,
        issues: $issuesResult->issues,
        attachments: $attachmentsResult->attachments,
        activities: $activitiesResult->page->items,
      );
    }


    $context = $this->localizeContext($context, $this->branding->getDocumentBranding($organizationId));

    $pdf = $this->renderer->render($context);

    $this->events->dispatch(new InterventionReportExportedEvent(
      interventionId: $id,
      organizationId: $organizationId,
      actorUserId: $command->userId,
    ));

    $reportNumber = $context['number'] ?? null;
    $fileName = sprintf('intervention-FG-%d-report.pdf', is_int($reportNumber) ? $reportNumber : 0);

    return new InterventionExportDocument(base64_encode($pdf), $fileName, 'application/pdf', $organizationId, $id);

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

    $memberNames = $this->resolveMemberNames($organizationId, $responsibleIri, $participantIris, $workItems, $activities);

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

    $changesByStatus = self::changesByStatus($changes);

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
      'reportMode' => 'published' === ($data['status'] ?? null) ? 'legacy_live' : 'live',
      'customerName' => null,
      'workItems' => self::mapWorkItems($workItems, $memberNames),
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
      'activities' => self::mapActivities($activities, $memberNames),
    ];
  }

  /**
   * @param list<InterventionWorkflowView> $changes proposed/applied/rejected changes
   *
   * @return array<string, int> counts by status
   */
  private static function changesByStatus(array $changes): array
  {
    $counts = ['proposed' => 0, 'applied' => 0, 'rejected' => 0];
    foreach ($changes as $change) {
      $status = $change->data['status'];
      $status = is_string($status) ? $status : 'proposed';
      $counts[$status] = ($counts[$status] ?? 0) + 1;
    }

    return $counts;
  }

  /**
   * @param list<InterventionWorkflowView> $workItems work items in report order
   * @param array<string, string> $memberNames resolved member names
   *
   * @return list<array<string, mixed>> template work items
   */
  private static function mapWorkItems(array $workItems, array $memberNames): array
  {
    return array_map(static function (InterventionWorkflowView $workItem) use ($memberNames): array {
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
        'executionResult' => $workItem->data['executionResult'] ?? null,
        'spentMinutes' => $workItem->data['spentMinutes'] ?? 0,
      ];
    }, $workItems);
  }

  /**
   * @param list<InterventionWorkflowView> $activities activities in report order
   * @param array<string, string> $memberNames resolved member names
   *
   * @return list<array<string, mixed>> template activities
   */
  private static function mapActivities(array $activities, array $memberNames): array
  {
    return array_map(static function (InterventionWorkflowView $activity) use ($memberNames): array {
      $actorIri = $activity->data['actor'] ?? null;
      $actorId = is_string($actorIri) ? ResourceIriParser::memberId($actorIri) : null;

      return [
        'createdAt' => $activity->data['createdAt'],
        'kind' => $activity->data['kind'],
        'event' => $activity->data['event'],
        'actorName' => null !== $actorId ? ($memberNames[$actorId] ?? null) : null,
        'body' => $activity->data['body'],
      ];
    }, $activities);
  }

  /**
   * @param list<string> $participantIris
   * @param list<InterventionWorkflowView> $workItems
   * @param list<InterventionWorkflowView> $activities
   *
   * @return array<string, string> member display names keyed by member id
   */
  private function resolveMemberNames(string $organizationId, ?string $responsibleIri, array $participantIris, array $workItems, array $activities): array
  {
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

    return $this->memberNaming->displayNamesFor($organizationId, array_values(array_unique($memberIds)));
  }

  /**
   * Method localizeContext.
   *
   * Enriches the Twig context with the organization document branding (name,
   * inlined logo, legal identity), the translation language, and dates
   * reformatted per the organization regional settings (timezone + date
   * format). Pure presentation shaping — no business decision.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $context the raw Twig context
   * @param OrganizationDocumentBranding $branding the organization document branding
   *
   * @return array<string, mixed> the localized Twig context
   */
  private function localizeContext(array $context, OrganizationDocumentBranding $branding): array
  {
    $formatter = new DocumentDateFormatter($branding->dateFormat, $branding->timezone);

    $context['org'] = [
      'name' => $branding->organizationName,
      'logoDataUri' => $branding->logoDataUri,
      'legalName' => $branding->legalName,
      'registrationNumber' => $branding->registrationNumber,
      'vatNumber' => $branding->vatNumber,
      'registeredAddress' => $branding->registeredAddress,
    ];
    $context['lang'] = $branding->language();

    $generatedAt = $context['generatedAt'] ?? null;
    $context['generatedAtFormatted'] = $formatter->formatDateTime(is_string($generatedAt) ? $generatedAt : null);

    foreach (['plannedStartAt', 'dueAt'] as $dateKey) {
      $value = $context[$dateKey] ?? null;
      $context[$dateKey] = $formatter->formatDateTime(is_string($value) ? $value : null);
    }

    if (isset($context['activities']) && is_array($context['activities'])) {
      $context['activities'] = array_map(
        static function (mixed $activity) use ($formatter): mixed {
          if (!is_array($activity)) {
            return $activity;
          }

          $createdAt = $activity['createdAt'] ?? null;
          $activity['createdAt'] = $formatter->formatDateTime(is_string($createdAt) ? $createdAt : null);

          return $activity;
        },
        $context['activities'],
      );
    }

    return $context;
  }

  private function authorize(string $userId, string $organizationId): void
  {
    $decision = $this->authorization->resolveAccess($userId, $organizationId, 'organization.interventions.read');
    if ($decision->isOutsideScope()) {
      throw new InterventionNotFoundException('Organization not found.');
    }
    if (!$decision->isGranted()) {
      throw new InterventionAccessDeniedException('Missing organization.interventions.read permission.');
    }
  }
}
