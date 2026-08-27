<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Query\ExportEquipments;

use Equipment\Application\Contract\Export\{EquipmentExportCandidate, EquipmentExportRow};
use Equipment\Application\Port\Outbound\{EquipmentRepositoryPort, FacilityNamingPort};
use Equipment\Domain\Exception\{EquipmentAccessDeniedException, EquipmentExportTooLargeException, EquipmentNotFoundException};
use Equipment\Domain\ValueObject\EquipmentOrganizationId;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\QueryHandler;

use function array_filter;
use function array_map;
use function array_unique;
use function array_values;

/**
 * UseCase ExportEquipmentsHandler.
 *
 * Bounds the export before resolving a single display name: a cheap COUNT
 * against the whole organization scope, rejecting the request with
 * {@see EquipmentExportTooLargeException} when it exceeds
 * {@see self::MAX_EXPORT_ROWS} — mirrors
 * `Intervention\Application\UseCase\Query\ExportInterventions\ExportInterventionsHandler`.
 * Under the cap, the matching rows are fetched once and the facility display
 * names are resolved in a single bulk round trip (never one query per row).
 *
 * Deliberately unfiltered: the equipment list endpoint's filters (facility,
 * type, status, brand, model, subType, search) are cheap to replicate as an
 * export criteria factory, but the export is intended as a full-organization
 * backup/reimport source (see the Import module's `EquipmentRowFactory`), so
 * it always scopes to the whole organization rather than to whatever the
 * caller currently has filtered on the list page.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ExportEquipmentsHandler implements QueryHandler
{
  // #region Constants
  /**
   * Constant MAX_EXPORT_ROWS.
   *
   * Hard cap on the number of equipment items a single export request may
   * match, mirroring `ExportInterventionsHandler::MAX_EXPORT_ROWS`.
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
   * @param EquipmentRepositoryPort $repository the equipment repository port
   * @param FacilityNamingPort $facilityNaming the facility naming port
   * @param OrganizationAuthorizationPort $authorization the authorization port
   */
  public function __construct(
    private EquipmentRepositoryPort $repository,
    private FacilityNamingPort $facilityNaming,
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
   * @param ExportEquipmentsQuery $query the query to handle
   *
   * @throws EquipmentNotFoundException when the caller is outside the organization's scope
   * @throws EquipmentAccessDeniedException when the caller lacks `organization.equipment.read`
   * @throws EquipmentExportTooLargeException when the organization has more than {@see self::MAX_EXPORT_ROWS} equipment items
   *
   * @return ExportEquipmentsResult the bounded, name-resolved export result
   */
  public function __invoke(ExportEquipmentsQuery $query): ExportEquipmentsResult
  {
    $decision = $this->authorization->resolveAccess($query->userId, $query->organizationId, 'organization.equipment.read');
    if ($decision->isOutsideScope()) {
      throw EquipmentNotFoundException::forOrganizationScope($query->organizationId);
    }
    if (!$decision->isGranted()) {
      throw new EquipmentAccessDeniedException('Missing organization.equipment.read permission.');
    }

    $organizationId = EquipmentOrganizationId::fromString($query->organizationId);

    $total = $this->repository->countEquipments($organizationId);
    if ($total > self::MAX_EXPORT_ROWS) {
      throw EquipmentExportTooLargeException::exceedsCap(matched: $total, maxRows: self::MAX_EXPORT_ROWS);
    }

    $candidates = $this->repository->listEquipmentExportCandidates($organizationId);

    $facilityNames = $this->facilityNaming->findNamesByIds($this->uniqueIds($candidates));

    $rows = array_map(
      static fn (EquipmentExportCandidate $candidate): EquipmentExportRow => new EquipmentExportRow(
        id: $candidate->id,
        type: $candidate->type,
        subType: $candidate->subType,
        brand: $candidate->brand,
        model: $candidate->model,
        serialNumber: $candidate->serialNumber,
        locationLabel: $candidate->locationLabel,
        status: $candidate->status,
        facilityId: $candidate->facilityId,
        facilityName: null === $candidate->facilityId ? null : ($facilityNames[$candidate->facilityId] ?? null),
        installedAt: $candidate->installedAt,
        commissionedAt: $candidate->commissionedAt,
        createdAt: $candidate->createdAt,
        updatedAt: $candidate->updatedAt,
      ),
      $candidates,
    );

    return new ExportEquipmentsResult($rows, $total);
  }

  /**
   * Method uniqueIds.
   *
   * @since 1.0.0
   *
   * @param list<EquipmentExportCandidate> $candidates the candidates value
   *
   * @return list<string> the unique, non-null facility identifiers
   */
  private function uniqueIds(array $candidates): array
  {
    return array_values(array_unique(array_filter(
      array_map(static fn (EquipmentExportCandidate $candidate): ?string => $candidate->facilityId, $candidates),
      static fn (?string $id): bool => null !== $id,
    )));
  }
  // #endregion
}
