<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Query\ExportEquipmentReport;

use DateTimeImmutable;
use Equipment\Application\Port\Outbound\{EquipmentReportEntitlementPort, EquipmentReportPdfRendererPort};
use Equipment\Application\UseCase\Query\Equipment\GetEquipment\{GetEquipmentQuery, GetEquipmentResult};
use Equipment\Application\UseCase\Query\Equipment\ListEquipmentAttachments\{ListEquipmentAttachmentsQuery, ListEquipmentAttachmentsResult};
use Equipment\Application\UseCase\Query\Equipment\ListMaintenanceLogs\{ListMaintenanceLogsQuery, ListMaintenanceLogsResult};
use Equipment\Domain\Event\Export\EquipmentReportExportedEvent;
use Equipment\Domain\Exception\{EquipmentAccessDeniedException, EquipmentMediaNotFoundException, EquipmentReportNotEntitledException};
use Organization\Application\Contract\Document\OrganizationDocumentBranding;
use Organization\Application\Port\Inbound\{OrganizationAuthorizationPort, OrganizationDocumentBrandingPort};
use Shared\Application\Contract\Document\DocumentDateFormatter;
use Shared\Application\Contract\Pagination\Pagination;
use Shared\Application\Message\QueryHandler;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Application\Port\Outbound\EventDispatcherPort;

use function array_map;
use function is_array;
use function is_string;
use function sprintf;

/** Owns report authorization, entitlement, rendering and export audit. @category UseCase @version 1.0.0 @author Valentin FORTIN <contact@valentin-fortin.pro> */
final readonly class ExportEquipmentReportHandler implements QueryHandler
{
  private const int MAX_ITEMS_PER_SECTION = 100;

  /**
   * @since 1.0.0
   */
  public function __construct(
    private QueryBusPort $queryBus,
    private OrganizationAuthorizationPort $authorization,
    private OrganizationDocumentBrandingPort $branding,
    private EquipmentReportEntitlementPort $entitlement,
    private EquipmentReportPdfRendererPort $renderer,
    private EventDispatcherPort $eventDispatcher,
  ) {
  }

  /**
   * @since 1.0.0
   */
  public function __invoke(ExportEquipmentReportQuery $query): ExportEquipmentReportResult
  {
    $organizationId = $query->organizationId;
    $equipmentId = $query->equipmentId;
    $decision = $this->authorization->resolveAccess($query->userId, $organizationId, 'organization.equipment.read');
    if ($decision->isOutsideScope()) {
      throw new EquipmentMediaNotFoundException('Organization not found.');
    }
    if (!$decision->isGranted()) {
      throw new EquipmentAccessDeniedException('Missing organization.equipment.read permission.');
    }
    if (!$this->entitlement->isExportEntitled($organizationId)) {
      throw EquipmentReportNotEntitledException::planTooLow($organizationId);
    }
    /** @var GetEquipmentResult $equipmentResult */
    $equipmentResult = $this->queryBus->ask(new GetEquipmentQuery($organizationId, $equipmentId));
    /** @var ListMaintenanceLogsResult $logsResult */
    $logsResult = $this->queryBus->ask(new ListMaintenanceLogsQuery($organizationId, $equipmentId, pagination: new Pagination(offset: 0, limit: self::MAX_ITEMS_PER_SECTION)));
    /** @var ListEquipmentAttachmentsResult $attachmentsResult */
    $attachmentsResult = $this->queryBus->ask(new ListEquipmentAttachmentsQuery($organizationId, $equipmentId));
    $planKey = $this->entitlement->resolvePlanKey($organizationId) ?? 'unknown';
    $context = $this->buildContext($equipmentResult, $logsResult, $attachmentsResult);
    $context['planKey'] = $planKey;
    $context = $this->localizeContext($context, $this->branding->getDocumentBranding($organizationId));
    $pdf = $this->renderer->render($context);
    $this->eventDispatcher->dispatch(new EquipmentReportExportedEvent($equipmentId, $organizationId, $query->userId, $planKey));

    return new ExportEquipmentReportResult($pdf, sprintf('equipment-%s-report.pdf', $equipmentId));
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
   * @param GetEquipmentResult $equipment the equipment detail result
   * @param ListMaintenanceLogsResult $logs the maintenance history result
   * @param ListEquipmentAttachmentsResult $attachments the attachment index result
   *
   * @return array<string, mixed> the Twig template context
   */
  private function buildContext(
    GetEquipmentResult $equipment,
    ListMaintenanceLogsResult $logs,
    ListEquipmentAttachmentsResult $attachments,
  ): array {
    return [
      'equipmentId' => $equipment->equipmentId,
      'name' => $equipment->name,
      'assetCode' => $equipment->assetCode,
      'criticality' => $equipment->criticality,
      'technicalProperties' => $equipment->technicalProperties,
      'predecessorEquipmentId' => $equipment->predecessorEquipmentId,
      'successorEquipmentId' => $equipment->successorEquipmentId,
      'type' => $equipment->type,
      'subType' => $equipment->subType,
      'brand' => $equipment->brand,
      'model' => $equipment->model,
      'serialNumber' => $equipment->serialNumber,
      'locationLabel' => $equipment->locationLabel,
      'status' => $equipment->status,
      'facilityName' => $equipment->facilityName,
      'installedAt' => $equipment->installedAt,
      'commissionedAt' => $equipment->commissionedAt,
      'maintenanceDueStatus' => $equipment->maintenanceDueStatus,
      'controlDueStatus' => $equipment->controlDueStatus ?? $equipment->maintenanceDueStatus,
      'serviceDueStatus' => $equipment->serviceDueStatus,
      'controlNextDueAt' => $equipment->controlNextDueAt,
      'serviceNextDueAt' => $equipment->serviceNextDueAt,
      'tags' => array_map(static fn (array $tag): string => (string) $tag['name'], $equipment->tags),
      'createdAt' => $equipment->createdAt->format('c'),
      'updatedAt' => $equipment->updatedAt->format('c'),
      'generatedAt' => new DateTimeImmutable()->format('c'),
      'maintenanceLogs' => array_map(static fn (array $log): array => [
        'startedAt' => $log['startedAt'],
        'completedAt' => $log['completedAt'],
        'source' => $log['source'],
        'interventionNumber' => $log['interventionNumber'],
        'workItemAction' => $log['workItemAction'],
        'summary' => $log['summary'],
      ], $logs->logs),
      'maintenanceLogsTotal' => $logs->total,
      'attachments' => array_map(static fn (array $attachment): array => [
        'fileName' => $attachment['fileName'],
        'mimeType' => $attachment['mimeType'],
        'size' => $attachment['size'],
        'label' => $attachment['label'],
        'uploadedAt' => $attachment['uploadedAt'],
      ], $attachments->attachments),
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

    foreach (['installedAt', 'commissionedAt', 'controlNextDueAt', 'serviceNextDueAt'] as $dateKey) {
      $value = $context[$dateKey] ?? null;
      $context[$dateKey] = $formatter->formatDate(is_string($value) ? $value : null);
    }

    foreach (['maintenanceLogs' => ['startedAt', 'completedAt'], 'attachments' => ['uploadedAt']] as $listKey => $dateKeys) {
      if (!isset($context[$listKey]) || !is_array($context[$listKey])) {
        continue;
      }

      $context[$listKey] = self::formatDatedRows($context[$listKey], $dateKeys, $formatter);
    }

    return $context;
  }

  /**
   * @param array<array-key, mixed> $rows
   * @param list<string> $dateKeys
   *
   * @return array<array-key, mixed>
   */
  private static function formatDatedRows(array $rows, array $dateKeys, DocumentDateFormatter $formatter): array
  {
    return array_map(
      static function (mixed $row) use ($formatter, $dateKeys): mixed {
        if (!is_array($row)) {
          return $row;
        }

        foreach ($dateKeys as $dateKey) {
          $value = $row[$dateKey] ?? null;
          $row[$dateKey] = $formatter->formatDateTime(is_string($value) ? $value : null);
        }

        return $row;
      },
      $rows,
    );
  }
  // #endregion
}
