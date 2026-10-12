<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Export\ExportNonConformitiesReportDocument;

use DateTimeImmutable;
use Inspection\Application\Contract\Event\Export\NonConformitiesReportExportedEvent;
use Inspection\Application\Contract\Export\{InspectionExportDocument, NonConformityExportRow};
use Inspection\Application\Port\Outbound\NonConformityReportPdfRendererPort;
use Inspection\Application\Service\NonConformityExportFilterNormalizer;
use Inspection\Application\UseCase\Query\ExportNonConformities\{ExportNonConformitiesQuery, ExportNonConformitiesResult};
use Inspection\Application\UseCase\Query\Report\ResolveInspectionReportAccess\{ResolveInspectionReportAccessQuery, ResolveInspectionReportAccessResult};
use Organization\Application\Contract\Document\OrganizationDocumentBranding;
use Organization\Application\Port\Inbound\OrganizationDocumentBrandingPort;
use Shared\Application\Contract\Document\DocumentDateFormatter;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Application\Port\Outbound\{EventDispatcherPort, TransactionManagerPort};

use function array_keys;
use function array_map;
use function base64_encode;
use function count;
use function is_array;
use function is_string;
use function sprintf;

/** Renders the original document and records its audit intent inside the same owning main transaction. @category UseCase */
final readonly class ExportNonConformitiesReportDocumentHandler implements CommandHandler
{
  private const array SEVERITY_ORDER = ['critical', 'high', 'medium', 'low'];

  public function __construct(
    private QueryBusPort $queryBus,
    private OrganizationDocumentBrandingPort $branding,
    private NonConformityReportPdfRendererPort $renderer,
    private EventDispatcherPort $events,
    private TransactionManagerPort $transactions,
  ) {
  }

  public function __invoke(ExportNonConformitiesReportDocumentCommand $command): InspectionExportDocument
  {
    return $this->transactions->transactional(fn (): InspectionExportDocument => $this->export($command));
  }

  private function export(ExportNonConformitiesReportDocumentCommand $command): InspectionExportDocument
  {
    $organizationId = $command->organizationId;

    /** @var ResolveInspectionReportAccessResult $access */
    $access = $this->queryBus->ask(new ResolveInspectionReportAccessQuery($command->userId, $organizationId));
    $filters = new NonConformityExportFilterNormalizer()->normalize($command->filters);




    /** @var ExportNonConformitiesResult $result */
    $result = $this->queryBus->ask(new ExportNonConformitiesQuery(
      userId: $command->userId,
      organizationId: $organizationId,
      filters: $filters,
    ));


    $planKey = $access->planKey;

    /** @var list<string> $filterKeys */
    $filterKeys = array_keys($filters);

    $context = $this->buildContext($result, $filters);
    $context['planKey'] = $planKey;
    $context = $this->localizeContext($context, $this->branding->getDocumentBranding($organizationId));

    $pdf = $this->renderer->render($context);

    $this->events->dispatch(new NonConformitiesReportExportedEvent(
      organizationId: $organizationId,
      actorUserId: $command->userId,
      rowCount: $result->total,
      filterKeys: $filterKeys,
      planKey: $planKey,
    ));

    $fileName = sprintf('non-conformities-report-%s.pdf', new DateTimeImmutable()->format('Ymd-His'));

    return new InspectionExportDocument(base64_encode($pdf), $fileName, 'application/pdf', $organizationId, null);

  }

  /**
   * Method buildContext.
   *
   * Translates the export rows into a flat Twig context grouped by
   * severity, most critical first — pure presentation shaping, the handler
   * already computed `ageInDays` and resolved every display name.
   *
   * @since 1.0.0
   *
   * @param ExportNonConformitiesResult $result the export result
   * @param array<string, mixed> $filters the applied filters
   *
   * @return array<string, mixed> the Twig template context
   */
  private function buildContext(ExportNonConformitiesResult $result, array $filters): array
  {
    $groups = [];
    foreach (self::SEVERITY_ORDER as $severity) {
      $groups[$severity] = [];
    }

    foreach ($result->rows as $row) {
      $groups[$row->severity] ??= [];
      $groups[$row->severity][] = $this->rowContext($row);
    }

    $severityGroups = [];
    foreach ($groups as $severity => $rows) {
      $severityGroups[] = [
        'severity' => $severity,
        'count' => count($rows),
        'rows' => $rows,
      ];
    }

    $severityFilter = $filters['severity'] ?? null;
    $statusFilter = $filters['status'] ?? null;

    return [
      'total' => $result->total,
      'severityFilter' => is_string($severityFilter) ? $severityFilter : null,
      'statusFilter' => is_string($statusFilter) ? $statusFilter : null,
      'severityGroups' => $severityGroups,
      'generatedAt' => new DateTimeImmutable()->format('c'),
    ];
  }

  /**
   * Method rowContext.
   *
   * @since 1.0.0
   *
   * @param NonConformityExportRow $row the export row
   *
   * @return array<string, mixed> the row's Twig context
   */
  private function rowContext(NonConformityExportRow $row): array
  {
    return [
      'status' => $row->status,
      'ageInDays' => $row->ageInDays,
      'facilityName' => $row->facilityName ?? $row->facilityId,
      'equipmentSerialNumber' => $row->equipmentSerialNumber ?? $row->equipmentId,
      'createdAt' => $row->createdAt,
      'resolvedAt' => $row->resolvedAt,
    ];
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

    if (isset($context['severityGroups']) && is_array($context['severityGroups'])) {
      $context['severityGroups'] = array_map(
        static fn (mixed $group): mixed => self::localizeSeverityGroup($group, $formatter),
        $context['severityGroups'],
      );
    }

    return $context;
  }

  /**
   * Method localizeSeverityGroup
   *
   * Formats the dates in each row of a severity group when the expected row list is present.
   *
   * @access private
   *
   * @param mixed $group report group to localize
   * @param DocumentDateFormatter $formatter formats report dates
   *
   * @return mixed localized group, or the original value when its shape is unexpected
   */
  private static function localizeSeverityGroup(mixed $group, DocumentDateFormatter $formatter): mixed
  {
    if (!is_array($group) || !isset($group['rows']) || !is_array($group['rows'])) {
      return $group;
    }

    $group['rows'] = array_map(
      static fn (mixed $row): mixed => self::localizeNonConformityRow($row, $formatter),
      $group['rows'],
    );

    return $group;
  }

  /**
   * Method localizeNonConformityRow
   *
   * Formats the created and resolved dates when the report row is an array.
   *
   * @access private
   *
   * @param mixed $row report row to localize
   * @param DocumentDateFormatter $formatter formats report dates
   *
   * @return mixed localized row, or the original value when it is not an array
   */
  private static function localizeNonConformityRow(mixed $row, DocumentDateFormatter $formatter): mixed
  {
    if (!is_array($row)) {
      return $row;
    }

    $createdAt = $row['createdAt'] ?? null;
    $row['createdAt'] = $formatter->formatDate(is_string($createdAt) ? $createdAt : null);

    $resolvedAt = $row['resolvedAt'] ?? null;
    $row['resolvedAt'] = $formatter->formatDate(is_string($resolvedAt) ? $resolvedAt : null);

    return $row;
  }
}
