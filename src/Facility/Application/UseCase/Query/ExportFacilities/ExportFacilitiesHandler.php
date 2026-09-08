<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Query\ExportFacilities;

use Facility\Application\Contract\Export\{FacilityExportCandidate, FacilityExportRow};
use Facility\Application\Port\Outbound\FacilityRepositoryPort;
use Facility\Domain\Exception\{FacilityAccessDeniedException, FacilityExportTooLargeException, FacilityNotFoundException};
use Facility\Domain\Model\Facility\Facility;
use Facility\Domain\ValueObject\{FacilityId, FacilityOrganizationId, FacilityStatus, FacilityType};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Contract\Sorting\{SortDirection, Sorting};
use Shared\Application\Message\QueryHandler;
use Shared\Domain\Exception\InvalidValueException;
use ValueError;

use function array_filter;
use function array_map;
use function array_unique;
use function array_values;
use function is_bool;
use function is_string;

/**
 * UseCase ExportFacilitiesHandler.
 *
 * Bounds the export before resolving a single parent code: a cheap COUNT
 * against the same filters {@see \Facility\Application\UseCase\Query\Facility\ListFacilities\ListFacilitiesHandler}
 * applies for the `facility` resource, rejecting the request with
 * {@see FacilityExportTooLargeException} when it exceeds
 * {@see self::MAX_EXPORT_ROWS} — mirrors
 * `Intervention\...\ExportInterventionsHandler`. Under the cap, the matching
 * facilities are fetched once (no intermediate "candidate" projection: unlike
 * Intervention's cross-context workflow gateway, `FacilityRepositoryPort`
 * already returns the full `Facility` aggregate in a single query) and the
 * parent facility codes are resolved in one bulk round trip, never one query
 * per row.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ExportFacilitiesHandler implements QueryHandler
{
  // #region Constants
  /**
   * Constant MAX_EXPORT_ROWS.
   *
   * Hard cap on the number of facilities a single export request may match,
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
   * @param FacilityRepositoryPort $facilityRepository the facility repository port
   * @param OrganizationAuthorizationPort $authorization the authorization port
   */
  public function __construct(
    private FacilityRepositoryPort $facilityRepository,
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
   * @param ExportFacilitiesQuery $query the query to handle
   *
   * @throws FacilityNotFoundException when the caller is outside the organization's scope
   * @throws FacilityAccessDeniedException when the caller lacks `organization.facilities.read`
   * @throws FacilityExportTooLargeException when the filters match more than {@see self::MAX_EXPORT_ROWS} facilities
   *
   * @return ExportFacilitiesResult the bounded, code-resolved export result
   */
  public function __invoke(ExportFacilitiesQuery $query): ExportFacilitiesResult
  {
    $decision = $this->authorization->resolveAccess($query->userId, $query->organizationId, 'organization.facilities.read');
    if ($decision->isOutsideScope()) {
      throw FacilityNotFoundException::forOrganizationScope($query->organizationId);
    }
    if (!$decision->isGranted()) {
      throw new FacilityAccessDeniedException('Missing organization.facilities.read permission.');
    }

    $rawType = $this->filterValue($query->filters, 'type');
    $rawStatus = $this->filterValue($query->filters, 'status');
    $rawParentFacilityId = $this->filterValue($query->filters, 'parentFacilityId');

    try {
      $organizationId = FacilityOrganizationId::fromString($query->organizationId);
      $type = null !== $rawType ? FacilityType::from($rawType)->value : null;
      $status = null !== $rawStatus ? FacilityStatus::from($rawStatus)->value : null;
      $parentFacilityId = null !== $rawParentFacilityId ? (string) FacilityId::fromString($rawParentFacilityId) : null;
    } catch (InvalidValueException|ValueError $exception) {
      throw InvalidValueException::because($exception->getMessage(), $exception);
    }

    $code = $this->filterValue($query->filters, 'code');
    $search = $this->filterValue($query->filters, 'search');
    $includeArchived = (bool) ($query->filters['includeArchived'] ?? false);
    $rootsOnly = (bool) ($query->filters['rootsOnly'] ?? false);
    $hasCoordinatesRaw = $query->filters['hasCoordinates'] ?? null;
    $hasCoordinates = is_bool($hasCoordinatesRaw) ? $hasCoordinatesRaw : null;

    if ($rootsOnly && null !== $parentFacilityId) {
      throw InvalidValueException::because('rootsOnly cannot be combined with parentFacilityId.');
    }

    $total = $this->facilityRepository->countByOrganizationId(
      organizationId: $organizationId,
      includeArchived: $includeArchived,
      type: $type,
      status: $status,
      parentFacilityId: $parentFacilityId,
      code: $code,
      search: $search,
      rootsOnly: $rootsOnly,
      hasCoordinates: $hasCoordinates,
    );

    if ($total > self::MAX_EXPORT_ROWS) {
      throw FacilityExportTooLargeException::exceedsCap(matched: $total, maxRows: self::MAX_EXPORT_ROWS);
    }

    $facilities = $this->facilityRepository->findByOrganizationId(
      organizationId: $organizationId,
      includeArchived: $includeArchived,
      type: $type,
      status: $status,
      parentFacilityId: $parentFacilityId,
      code: $code,
      search: $search,
      sorting: new Sorting('name', SortDirection::ASC),
      limit: self::MAX_EXPORT_ROWS,
      offset: 0,
      rootsOnly: $rootsOnly,
      hasCoordinates: $hasCoordinates,
    );

    $candidates = array_map(static fn (Facility $facility): FacilityExportCandidate => new FacilityExportCandidate(
      id: (string) $facility->id(),
      type: $facility->type()->value,
      name: (string) $facility->name(),
      code: $facility->code(),
      address: $facility->address(),
      latitude: $facility->coordinates()?->latitude(),
      longitude: $facility->coordinates()?->longitude(),
      parentFacilityId: $facility->parentFacilityId()?->__toString(),
      status: $facility->status()->value,
      createdAt: $facility->createdAt()->format('c'),
      updatedAt: $facility->updatedAt()->format('c'),
      levelIndex: $facility->levelIndex(),
    ), $facilities);

    $parentIds = $this->uniqueIds($candidates, static fn (FacilityExportCandidate $candidate): ?string => $candidate->parentFacilityId);
    $parentCodes = $this->facilityRepository->getFacilityCodesByIds($organizationId, $parentIds);

    $rows = array_map(
      static fn (FacilityExportCandidate $candidate): FacilityExportRow => new FacilityExportRow(
        id: $candidate->id,
        type: $candidate->type,
        name: $candidate->name,
        code: $candidate->code,
        address: $candidate->address,
        latitude: $candidate->latitude,
        longitude: $candidate->longitude,
        parentCode: null === $candidate->parentFacilityId ? null : ($parentCodes[$candidate->parentFacilityId] ?? null),
        status: $candidate->status,
        createdAt: $candidate->createdAt,
        updatedAt: $candidate->updatedAt,
        levelIndex: $candidate->levelIndex,
      ),
      $candidates,
    );

    return new ExportFacilitiesResult($rows, $total);
  }

  /**
   * Method filterValue.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $filters the raw filters value
   * @param string $key the filter key
   *
   * @return ?string the string filter value, when present and non-empty
   */
  private function filterValue(array $filters, string $key): ?string
  {
    $value = $filters[$key] ?? null;
    if (!is_string($value) || '' === $value) {
      return null;
    }

    return $value;
  }

  /**
   * Method uniqueIds.
   *
   * @since 1.0.0
   *
   * @param list<FacilityExportCandidate> $candidates the candidates value
   * @param callable(FacilityExportCandidate): ?string $extract the field extractor
   *
   * @return list<string> the unique, non-null identifiers
   */
  private function uniqueIds(array $candidates, callable $extract): array
  {
    return array_values(array_unique(array_filter(array_map($extract, $candidates), static fn (?string $id): bool => null !== $id)));
  }
  // #endregion
}
