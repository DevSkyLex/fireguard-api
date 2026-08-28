<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Query\ExportEquipmentLabels;

use Equipment\Application\Contract\Export\{EquipmentExportCandidate, EquipmentExportRow};
use Equipment\Application\Port\Outbound\{EquipmentRepositoryPort, FacilityNamingPort};
use Equipment\Domain\Exception\{EquipmentAccessDeniedException, EquipmentLabelExportTooLargeException, EquipmentNotFoundException};
use Equipment\Domain\ValueObject\EquipmentOrganizationId;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\QueryHandler;

use function array_filter;
use function array_map;
use function array_unique;
use function array_values;
use function count;

/**
 * UseCase ExportEquipmentLabelsHandler.
 *
 * Bounds the QR label sheet before resolving a single display name: a cheap
 * COUNT against the requested selection, rejecting the request with
 * {@see EquipmentLabelExportTooLargeException} when it exceeds
 * {@see self::MAX_LABELS} — the same fail-fast shape as
 * {@see \Equipment\Application\UseCase\Query\ExportEquipments\ExportEquipmentsHandler}.
 * Under the cap, the matching rows are fetched once and the facility display
 * names are resolved in a single bulk round trip (never one query per row).
 *
 * Selection modes, mutually exclusive: an explicit equipment id list, one
 * facility, or (neither) the whole organization park. Identifiers outside
 * the organization's scope never match — the repository always applies the
 * organization filter, so a foreign id silently yields no label.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ExportEquipmentLabelsHandler implements QueryHandler
{
  // #region Constants
  /**
   * Constant MAX_LABELS.
   *
   * Hard cap on the number of labels a single sheet request may render.
   * 500 labels is ~21 A4 pages of 24 labels — beyond that the request is a
   * bulk print job that must be split per facility, and the dompdf render
   * time stops being interactive.
   *
   * @since 1.0.0
   *
   * @var int
   */
  public const int MAX_LABELS = 500;
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
   * @param ExportEquipmentLabelsQuery $query the query to handle
   *
   * @throws EquipmentNotFoundException when the caller is outside the organization's scope
   * @throws EquipmentAccessDeniedException when the caller lacks `organization.equipment.read`
   * @throws InvalidArgumentException when both selection modes are provided, or the id list is empty
   * @throws EquipmentLabelExportTooLargeException when the selection matches more than {@see self::MAX_LABELS} items
   *
   * @return ExportEquipmentLabelsResult the bounded, name-resolved label rows
   */
  public function __invoke(ExportEquipmentLabelsQuery $query): ExportEquipmentLabelsResult
  {
    $decision = $this->authorization->resolveAccess($query->userId, $query->organizationId, 'organization.equipment.read');
    if ($decision->isOutsideScope()) {
      throw EquipmentNotFoundException::forOrganizationScope($query->organizationId);
    }
    if (!$decision->isGranted()) {
      throw new EquipmentAccessDeniedException('Missing organization.equipment.read permission.');
    }

    if (null !== $query->equipmentIds && null !== $query->facilityId) {
      throw new InvalidArgumentException('Provide either ids[] or facilityId, not both.');
    }
    if (null !== $query->equipmentIds && [] === $query->equipmentIds) {
      throw new InvalidArgumentException('ids[] must not be empty when provided.');
    }

    $equipmentIds = null === $query->equipmentIds
      ? null
      : array_values(array_unique($query->equipmentIds));

    if (null !== $equipmentIds && count($equipmentIds) > self::MAX_LABELS) {
      throw EquipmentLabelExportTooLargeException::exceedsCap(matched: count($equipmentIds), maxLabels: self::MAX_LABELS);
    }

    $organizationId = EquipmentOrganizationId::fromString($query->organizationId);

    $total = $this->repository->countEquipmentLabelCandidates($organizationId, $equipmentIds, $query->facilityId);
    if ($total > self::MAX_LABELS) {
      throw EquipmentLabelExportTooLargeException::exceedsCap(matched: $total, maxLabels: self::MAX_LABELS);
    }

    $candidates = $this->repository->listEquipmentLabelCandidates($organizationId, $equipmentIds, $query->facilityId);

    $facilityNames = $this->facilityNaming->findNamesByIds($this->uniqueFacilityIds($candidates));

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

    $selection = null !== $equipmentIds ? 'ids' : (null !== $query->facilityId ? 'facility' : 'organization');

    return new ExportEquipmentLabelsResult($rows, $total, $selection);
  }

  /**
   * Method uniqueFacilityIds.
   *
   * @since 1.0.0
   *
   * @param list<EquipmentExportCandidate> $candidates the candidates value
   *
   * @return list<string> the unique, non-null facility identifiers
   */
  private function uniqueFacilityIds(array $candidates): array
  {
    return array_values(array_unique(array_filter(
      array_map(static fn (EquipmentExportCandidate $candidate): ?string => $candidate->facilityId, $candidates),
      static fn (?string $id): bool => null !== $id,
    )));
  }
  // #endregion
}
