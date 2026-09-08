<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\ExportNonConformities;

use DateTimeImmutable;
use Inspection\Application\Contract\Export\{NonConformityExportCandidate, NonConformityExportRow};
use Inspection\Application\Port\Outbound\{EquipmentNamingPort, FacilityNamingPort, NonConformityRepositoryPort};
use Inspection\Domain\Exception\{InspectionAccessDeniedException, InspectionExportTooLargeException, InspectionNotFoundException};
use Inspection\Domain\ValueObject\InspectionOrganizationId;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\QueryHandler;
use Shared\Application\Port\Outbound\ClockPort;

use function array_filter;
use function array_map;
use function array_unique;
use function array_values;
use function is_string;
use function max;

/**
 * UseCase ExportNonConformitiesHandler.
 *
 * Bounds the export before resolving a single display name: a cheap COUNT
 * against the same `severity`/`status` filter subset
 * {@see \Inspection\Presentation\Api\Provider\NonConformity\ListOrganizationNonConformitiesProvider}
 * applies, rejecting the request with {@see InspectionExportTooLargeException}
 * when it exceeds {@see self::MAX_EXPORT_ROWS} — mirrors
 * `Intervention\...\ExportInterventionsHandler` and
 * `Inspection\...\ExportInspectionsHandler`. Under the cap, the matching
 * rows are fetched once (the owning inspection's `facilityId`/`equipmentId`
 * resolved in the same query) and the facility/equipment display names are
 * resolved in two bulk round trips. `ageInDays` is computed here, against
 * {@see ClockPort}, never in the Presentation-layer CSV writer.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ExportNonConformitiesHandler implements QueryHandler
{
  // #region Constants
  /**
   * Constant MAX_EXPORT_ROWS.
   *
   * Hard cap on the number of non-conformities a single export request may
   * match, mirroring `Intervention\...\ExportInterventionsHandler::MAX_EXPORT_ROWS`.
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
   * @param NonConformityRepositoryPort $nonConformityRepository the non-conformity repository port
   * @param FacilityNamingPort $facilityNaming the facility naming port
   * @param EquipmentNamingPort $equipmentNaming the equipment naming port
   * @param OrganizationAuthorizationPort $authorization the authorization port
   * @param ClockPort $clock the clock port, resolving `now` for the `ageInDays` column
   */
  public function __construct(
    private NonConformityRepositoryPort $nonConformityRepository,
    private FacilityNamingPort $facilityNaming,
    private EquipmentNamingPort $equipmentNaming,
    private OrganizationAuthorizationPort $authorization,
    private ClockPort $clock,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param ExportNonConformitiesQuery $query the query to handle
   *
   * @throws InspectionNotFoundException when the caller is outside the organization's scope
   * @throws InspectionAccessDeniedException when the caller lacks `organization.inspection.read`
   * @throws InspectionExportTooLargeException when the filters match more than {@see self::MAX_EXPORT_ROWS} non-conformities
   *
   * @return ExportNonConformitiesResult the bounded, name-resolved export result
   */
  public function __invoke(ExportNonConformitiesQuery $query): ExportNonConformitiesResult
  {
    $decision = $this->authorization->resolveAccess($query->userId, $query->organizationId, 'organization.inspection.read');
    if ($decision->isOutsideScope()) {
      throw InspectionNotFoundException::forOrganizationScope($query->organizationId);
    }
    if (!$decision->isGranted()) {
      throw new InspectionAccessDeniedException('Missing organization.inspection.read permission.');
    }

    $organizationId = InspectionOrganizationId::fromString($query->organizationId);
    $severity = $this->stringFilter($query->filters, 'severity');
    $status = $this->stringFilter($query->filters, 'status');

    $total = $this->nonConformityRepository->countExportCandidates($organizationId, $severity, $status);
    if ($total > self::MAX_EXPORT_ROWS) {
      throw InspectionExportTooLargeException::exceedsCap(matched: $total, maxRows: self::MAX_EXPORT_ROWS);
    }

    $candidates = $this->nonConformityRepository->listExportCandidates($organizationId, $severity, $status);

    $facilityIds = $this->uniqueIds($candidates, static fn (NonConformityExportCandidate $candidate): ?string => $candidate->facilityId);
    $equipmentIds = $this->uniqueIds($candidates, static fn (NonConformityExportCandidate $candidate): ?string => $candidate->equipmentId);

    $facilityNames = $this->facilityNaming->findNamesByIds($facilityIds);
    $equipmentSerials = $this->equipmentNaming->findSerialNumbersByIds($equipmentIds);

    $now = $this->clock->now();

    $rows = array_map(
      fn (NonConformityExportCandidate $candidate): NonConformityExportRow => new NonConformityExportRow(
        id: $candidate->id,
        severity: $candidate->severity,
        status: $candidate->status,
        ageInDays: $this->ageInDays($candidate->createdAt, $now),
        facilityId: $candidate->facilityId,
        facilityName: null === $candidate->facilityId ? null : ($facilityNames[$candidate->facilityId] ?? null),
        equipmentId: $candidate->equipmentId,
        equipmentSerialNumber: null === $candidate->equipmentId ? null : ($equipmentSerials[$candidate->equipmentId] ?? null),
        inspectionId: $candidate->inspectionId,
        createdAt: $candidate->createdAt,
        resolvedAt: $candidate->resolvedAt,
      ),
      $candidates,
    );

    return new ExportNonConformitiesResult($rows, $total);
  }

  /**
   * Method ageInDays.
   *
   * @since 1.0.0
   *
   * @param string $createdAt the ISO 8601 creation date
   * @param DateTimeImmutable $now the reference instant
   *
   * @return int the whole number of days elapsed, never negative
   */
  private function ageInDays(string $createdAt, DateTimeImmutable $now): int
  {
    $created = new DateTimeImmutable($createdAt);

    return max(0, (int) $created->diff($now)->days);
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
   * @param list<NonConformityExportCandidate> $candidates the candidates value
   * @param callable(NonConformityExportCandidate): ?string $extract the field extractor
   *
   * @return list<string> the unique, non-null identifiers
   */
  private function uniqueIds(array $candidates, callable $extract): array
  {
    return array_values(array_unique(array_filter(array_map($extract, $candidates), static fn (?string $id): bool => null !== $id)));
  }
  // #endregion
}
