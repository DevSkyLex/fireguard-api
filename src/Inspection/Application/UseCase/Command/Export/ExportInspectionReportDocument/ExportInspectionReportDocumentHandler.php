<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Export\ExportInspectionReportDocument;

use DateTimeImmutable;
use Inspection\Application\Contract\Event\Export\InspectionReportExportedEvent;
use Inspection\Application\Contract\Export\InspectionExportDocument;
use Inspection\Application\Contract\Response\InspectionResponseView;
use Inspection\Application\Port\Outbound\InspectionReportPdfRendererPort;
use Inspection\Application\UseCase\Query\Inspection\GetInspection\{GetInspectionQuery, GetInspectionResult};
use Inspection\Application\UseCase\Query\NonConformity\ListNonConformities\{ListNonConformitiesQuery, NonConformityResult};
use Inspection\Application\UseCase\Query\Report\ResolveInspectionReportAccess\{ResolveInspectionReportAccessQuery, ResolveInspectionReportAccessResult};
use Inspection\Application\UseCase\Query\Response\ListInspectionResponses\{ListInspectionResponsesQuery, ListInspectionResponsesResult};
use Organization\Application\Contract\Document\OrganizationDocumentBranding;
use Organization\Application\Port\Inbound\OrganizationDocumentBrandingPort;
use Shared\Application\Contract\Document\DocumentDateFormatter;
use Shared\Application\Contract\Pagination\{PaginatedResult, Pagination};
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Application\Port\Outbound\{EventDispatcherPort, TransactionManagerPort};

use function array_map;
use function base64_encode;
use function implode;
use function is_array;
use function is_bool;
use function is_scalar;
use function is_string;
use function sprintf;

/** Renders the original document and records its audit intent inside the same owning main transaction. @category UseCase */
final readonly class ExportInspectionReportDocumentHandler implements CommandHandler
{
  private const int MAX_ITEMS_PER_SECTION = 200;

  public function __construct(
    private QueryBusPort $queryBus,
    private OrganizationDocumentBrandingPort $branding,
    private InspectionReportPdfRendererPort $renderer,
    private EventDispatcherPort $events,
    private TransactionManagerPort $transactions,
  ) {
  }

  public function __invoke(ExportInspectionReportDocumentCommand $command): InspectionExportDocument
  {
    return $this->transactions->transactional(fn (): InspectionExportDocument => $this->export($command));
  }

  private function export(ExportInspectionReportDocumentCommand $command): InspectionExportDocument
  {
    $organizationId = $command->organizationId;
    $inspectionId = $command->resourceId;
    /** @var ResolveInspectionReportAccessResult $access */
    $access = $this->queryBus->ask(new ResolveInspectionReportAccessQuery($command->userId, $organizationId));


    /** @var GetInspectionResult $inspectionResult */
    $inspectionResult = $this->queryBus->ask(new GetInspectionQuery(
      organizationId: $organizationId,
      inspectionId: $inspectionId,
    ));

    /** @var ListInspectionResponsesResult $responsesResult */
    $responsesResult = $this->queryBus->ask(new ListInspectionResponsesQuery(
      organizationId: $organizationId,
      inspectionId: $inspectionId,
      page: 1,
      itemsPerPage: self::MAX_ITEMS_PER_SECTION,
    ));

    /** @var PaginatedResult<NonConformityResult> $nonConformitiesResult */
    $nonConformitiesResult = $this->queryBus->ask(new ListNonConformitiesQuery(
      organizationId: $organizationId,
      inspectionId: $inspectionId,
      pagination: new Pagination(offset: 0, limit: self::MAX_ITEMS_PER_SECTION),
    ));


    $planKey = $access->planKey;

    $context = $this->buildContext($inspectionResult, $responsesResult, $nonConformitiesResult);
    $context['planKey'] = $planKey;
    $context = $this->localizeContext($context, $this->branding->getDocumentBranding($organizationId));

    $pdf = $this->renderer->render($context);

    $this->events->dispatch(new InspectionReportExportedEvent(
      inspectionId: $inspectionId,
      organizationId: $organizationId,
      actorUserId: $command->userId,
      planKey: $planKey,
    ));

    $fileName = sprintf('inspection-%s-report.pdf', $inspectionId);

    return new InspectionExportDocument(base64_encode($pdf), $fileName, 'application/pdf', $organizationId, $inspectionId);

  }

  /**
   * Method buildContext.
   *
   * Translates the query results into a flat Twig context — pure
   * presentation shaping, nothing here decides anything the handlers have
   * not already decided.
   *
   * @since 1.0.0
   *
   * @param GetInspectionResult $inspection the inspection detail result
   * @param ListInspectionResponsesResult $responses the checklist responses result
   * @param PaginatedResult<NonConformityResult> $nonConformities the inspection's non-conformities result
   *
   * @return array<string, mixed> the Twig template context
   */
  private function buildContext(
    GetInspectionResult $inspection,
    ListInspectionResponsesResult $responses,
    PaginatedResult $nonConformities,
  ): array {
    return [
      'inspectionId' => $inspection->inspectionId,
      'status' => $inspection->status,
      'result' => $inspection->result,
      'performedAt' => $inspection->performedAt,
      'inspectorType' => $inspection->inspectorType,
      'inspectorName' => $inspection->inspectorName,
      'inspectorOrganizationName' => $inspection->inspectorOrganizationName,
      'facilityName' => $inspection->facilityName,
      'equipmentSerialNumber' => $inspection->equipmentSerialNumber,
      'checklistName' => $inspection->checklistName,
      'notes' => $inspection->notes,
      'hasSignature' => null !== $inspection->signature && '' !== $inspection->signature,
      'nonConformitiesCount' => $inspection->nonConformitiesCount,
      'generatedAt' => new DateTimeImmutable()->format('c'),
      'responses' => array_map(static fn (InspectionResponseView $view): array => [
        'itemKey' => $view->itemKey,
        'value' => self::stringifyResponseValue($view->value),
      ], $responses->views),
      'nonConformities' => array_map(static fn (NonConformityResult $nonConformity): array => [
        'severity' => $nonConformity->severity,
        'status' => $nonConformity->status,
        'description' => $nonConformity->description,
        'dueAt' => $nonConformity->dueAt,
        'resolvedAt' => $nonConformity->resolvedAt,
        'createdAt' => $nonConformity->createdAt->format('c'),
      ], $nonConformities->items),
    ];
  }

  /**
   * Method stringifyResponseValue.
   *
   * Renders a checklist answer (free-form JSON: bool, number, string,
   * list…) as a display string — presentation shaping only.
   *
   * @since 1.0.0
   *
   * @param mixed $value the raw stored answer
   *
   * @return string the display string
   */
  private static function stringifyResponseValue(mixed $value): string
  {
    $result = '';
    if (is_bool($value)) {
      $result = $value ? 'yes' : 'no';
    } elseif (is_scalar($value)) {
      $result = (string) $value;
    } elseif (is_array($value)) {
      $parts = [];
      foreach ($value as $item) {
        if (is_scalar($item)) {
          $parts[] = (string) $item;
        }
      }
      $result = implode(', ', $parts);
    }

    return $result;
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

    $performedAt = $context['performedAt'] ?? null;
    $context['performedAt'] = $formatter->formatDateTime(is_string($performedAt) ? $performedAt : null);

    if (isset($context['nonConformities']) && is_array($context['nonConformities'])) {
      $context['nonConformities'] = array_map(
        static fn (mixed $nonConformity): mixed => self::localizeNonConformity($nonConformity, $formatter),
        $context['nonConformities'],
      );
    }

    return $context;
  }

  /**
   * Method localizeNonConformity.
   *
   * Formats due and resolution dates in a non-conformity row while preserving non-array values.
   *
   * @access private
   *
   * @static
   *
   * @param mixed $nonConformity the report row to localize
   * @param DocumentDateFormatter $formatter formats report dates
   *
   * @return mixed the localized row or the original non-array value
   */
  private static function localizeNonConformity(mixed $nonConformity, DocumentDateFormatter $formatter): mixed
  {
    if (!is_array($nonConformity)) {
      return $nonConformity;
    }

    foreach (['dueAt', 'resolvedAt'] as $dateKey) {
      $value = $nonConformity[$dateKey] ?? null;
      $nonConformity[$dateKey] = $formatter->formatDate(is_string($value) ? $value : null);
    }

    $createdAt = $nonConformity['createdAt'] ?? null;
    $nonConformity['createdAt'] = $formatter->formatDateTime(is_string($createdAt) ? $createdAt : null);

    return $nonConformity;
  }
}
