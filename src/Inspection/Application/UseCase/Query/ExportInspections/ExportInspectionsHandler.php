<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\ExportInspections;

use Inspection\Application\Contract\Export\{InspectionExportCandidate, InspectionExportRow};
use Inspection\Application\Port\Outbound\{ChecklistRepositoryPort, EquipmentNamingPort, FacilityNamingPort, InspectionRepositoryPort, NonConformityRepositoryPort};
use Inspection\Domain\Exception\{InspectionAccessDeniedException, InspectionExportTooLargeException, InspectionNotFoundException};
use Inspection\Domain\ValueObject\InspectionOrganizationId;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\QueryHandler;

use function array_filter;
use function array_map;
use function array_unique;
use function array_values;
use function is_string;

/**
 * UseCase ExportInspectionsHandler.
 *
 * Bounds the export before resolving a single display name: a cheap COUNT
 * against the same filter subset {@see \Inspection\Presentation\Api\Provider\Inspection\ListInspectionsProvider}
 * applies for the `inspection` resource, rejecting the request with
 * {@see InspectionExportTooLargeException} when it exceeds
 * {@see self::MAX_EXPORT_ROWS} — mirrors
 * `Intervention\...\ExportInterventionsHandler`. Under the cap, the matching
 * rows are fetched once and the facility/equipment/checklist display names
 * and the non-conformity counters are resolved in four bulk round trips
 * (never one query per row).
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ExportInspectionsHandler implements QueryHandler
{
  // #region Constants
  /**
   * Constant MAX_EXPORT_ROWS.
   *
   * Hard cap on the number of inspections a single export request may match,
   * mirroring `Intervention\...\ExportInterventionsHandler::MAX_EXPORT_ROWS`.
   *
   * @since 1.0.0
   *
   * @var int
   */
  public const int MAX_EXPORT_ROWS = 50_000;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param InspectionRepositoryPort $inspectionRepository the inspection repository port
   * @param NonConformityRepositoryPort $nonConformityRepository the non-conformity repository port
   * @param FacilityNamingPort $facilityNaming the facility naming port
   * @param EquipmentNamingPort $equipmentNaming the equipment naming port
   * @param ChecklistRepositoryPort $checklistRepository the checklist repository port, used only for its bulk name lookup
   * @param OrganizationAuthorizationPort $authorization the authorization port
   */
  public function __construct(
    private InspectionRepositoryPort $inspectionRepository,
    private NonConformityRepositoryPort $nonConformityRepository,
    private FacilityNamingPort $facilityNaming,
    private EquipmentNamingPort $equipmentNaming,
    private ChecklistRepositoryPort $checklistRepository,
    private OrganizationAuthorizationPort $authorization,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param ExportInspectionsQuery $query the query to handle
   *
   * @throws InspectionNotFoundException when the caller is outside the organization's scope
   * @throws InspectionAccessDeniedException when the caller lacks `organization.inspection.read`
   * @throws InspectionExportTooLargeException when the filters match more than {@see self::MAX_EXPORT_ROWS} inspections
   *
   * @return ExportInspectionsResult the bounded, name-resolved export result
   */
  public function __invoke(ExportInspectionsQuery $query): ExportInspectionsResult
  {
    $decision = $this->authorization->resolveAccess($query->userId, $query->organizationId, 'organization.inspection.read');
    if ($decision->isOutsideScope()) {
      throw InspectionNotFoundException::forOrganizationScope($query->organizationId);
    }
    if (!$decision->isGranted()) {
      throw new InspectionAccessDeniedException('Missing organization.inspection.read permission.');
    }

    $organizationId = InspectionOrganizationId::fromString($query->organizationId);
    $filters = $query->filters;
    $equipmentId = $this->stringFilter($filters, 'equipmentId');
    $facilityId = $this->stringFilter($filters, 'facilityId');
    $result = $this->stringFilter($filters, 'result');
    $status = $this->stringFilter($filters, 'status');
    $performedAtFrom = $this->stringFilter($filters, 'performedAtFrom');
    $performedAtTo = $this->stringFilter($filters, 'performedAtTo');
    $inspectorUserId = $this->stringFilter($filters, 'inspectorUserId');
    $checklistId = $this->stringFilter($filters, 'checklistId');

    $total = $this->inspectionRepository->countExportCandidates(
      $organizationId,
      $equipmentId,
      $facilityId,
      $result,
      $status,
      $performedAtFrom,
      $performedAtTo,
      $inspectorUserId,
      $checklistId,
    );
    if ($total > self::MAX_EXPORT_ROWS) {
      throw InspectionExportTooLargeException::exceedsCap(matched: $total, maxRows: self::MAX_EXPORT_ROWS);
    }

    $candidates = $this->inspectionRepository->listExportCandidates(
      $organizationId,
      $equipmentId,
      $facilityId,
      $result,
      $status,
      $performedAtFrom,
      $performedAtTo,
      $inspectorUserId,
      $checklistId,
    );

    $inspectionIds = array_map(static fn (InspectionExportCandidate $candidate): string => $candidate->id, $candidates);
    $facilityIds = $this->uniqueIds($candidates, static fn (InspectionExportCandidate $candidate): ?string => $candidate->facilityId);
    $equipmentIds = array_values(array_unique(array_map(static fn (InspectionExportCandidate $candidate): string => $candidate->equipmentId, $candidates)));
    $checklistIds = $this->uniqueIds($candidates, static fn (InspectionExportCandidate $candidate): ?string => $candidate->checklistId);

    $facilityNames = $this->facilityNaming->findNamesByIds($facilityIds);
    $equipmentSerials = $this->equipmentNaming->findSerialNumbersByIds($equipmentIds);
    $checklistNames = $this->checklistRepository->findNamesByIds($checklistIds);
    $openCounts = $this->nonConformityRepository->countsOpenByInspectionIds($inspectionIds);
    $totalCounts = $this->nonConformityRepository->countsByInspectionIds($inspectionIds);

    $rows = array_map(
      static fn (InspectionExportCandidate $candidate): InspectionExportRow => new InspectionExportRow(
        id: $candidate->id,
        status: $candidate->status,
        result: $candidate->result,
        facilityId: $candidate->facilityId,
        facilityName: null === $candidate->facilityId ? null : ($facilityNames[$candidate->facilityId] ?? null),
        equipmentId: $candidate->equipmentId,
        equipmentSerialNumber: $equipmentSerials[$candidate->equipmentId] ?? null,
        checklistId: $candidate->checklistId,
        checklistName: null === $candidate->checklistId ? null : ($checklistNames[$candidate->checklistId] ?? null),
        performedAt: $candidate->performedAt,
        nonConformitiesOpen: $openCounts[$candidate->id] ?? 0,
        nonConformitiesTotal: $totalCounts[$candidate->id] ?? 0,
        createdAt: $candidate->createdAt,
        updatedAt: $candidate->updatedAt,
      ),
      $candidates,
    );

    return new ExportInspectionsResult($rows, $total);
  }

  /**
   * Method stringFilter.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $filters the raw filters value
   * @param string $key the filter key
   *
   * @return ?string the string filter result
   */
  private function stringFilter(array $filters, string $key): ?string
  {
    $value = $filters[$key] ?? null;

    return is_string($value) && '' !== $value ? $value : null;
  }

  /**
   * Method uniqueIds.
   *
   * @since 1.0.0
   *
   * @param list<InspectionExportCandidate> $candidates the candidates value
   * @param callable(InspectionExportCandidate): ?string $extract the field extractor
   *
   * @return list<string> the unique, non-null identifiers
   */
  private function uniqueIds(array $candidates, callable $extract): array
  {
    return array_values(array_unique(array_filter(array_map($extract, $candidates), static fn (?string $id): bool => null !== $id)));
  }
  // #endregion
}
