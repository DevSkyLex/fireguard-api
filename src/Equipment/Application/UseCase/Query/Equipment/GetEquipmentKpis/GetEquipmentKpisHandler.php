<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Query\Equipment\GetEquipmentKpis;

use Equipment\Application\Port\Outbound\{EquipmentRepositoryPort, MaintenanceDueStatusPort, NonConformityStatisticsPort};
use Equipment\Domain\Model\Equipment\Equipment;
use Equipment\Domain\ValueObject\EquipmentOrganizationId;
use Shared\Application\Message\QueryHandler;

use function array_map;

/**
 * UseCase GetEquipmentKpisHandler.
 *
 * Builds the four headline counters shown together on the mockup's Equipment
 * page: total assets, compliant (maintenance up to date), due soon, and
 * organization-wide open non-conformities.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetEquipmentKpisHandler implements QueryHandler
{
  // #region Constants
  /**
   * Upper bound for the in-memory due-status tally below. Mirrors
   * `ListEquipmentsHandler::DUE_STATUS_FILTER_SCAN_LIMIT` — organizations
   * are plan-quota bounded on equipment count, so this is a generous safety
   * net, not a practical limit.
   *
   * @var int
   */
  private const int DUE_STATUS_SCAN_LIMIT = 10_000;
  // #endregion

  // #region Constructor
  public function __construct(
    private EquipmentRepositoryPort $equipmentRepository,
    private MaintenanceDueStatusPort $maintenanceDueStatusPort,
    private NonConformityStatisticsPort $nonConformityStatistics,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   */
  public function __invoke(GetEquipmentKpisQuery $query): GetEquipmentKpisResult
  {
    $organizationId = EquipmentOrganizationId::fromString($query->organizationId);

    $overview = $this->equipmentRepository->countOverviewByOrganizationId($organizationId);
    $totalAssets = $overview['total'];

    // The due status lives in the Maintenance module, not in the `equipment`
    // table: it cannot be pushed into SQL. Every equipment id for the
    // organization is resolved (up to the safety-net scan limit) and the due
    // status for the whole candidate set is batch-resolved in ONE call —
    // never per row — mirroring `ListEquipmentsHandler::listFilteredByDueStatus()`.
    $equipments = $this->equipmentRepository->findByOrganizationId(
      $organizationId,
      limit: self::DUE_STATUS_SCAN_LIMIT,
      offset: 0,
    );
    $equipmentIds = array_map(
      static fn (Equipment $equipment): string => (string) $equipment->id(),
      $equipments,
    );

    $dueStatusesByEquipmentId = $this->maintenanceDueStatusPort->dueStatusesForEquipment(
      (string) $organizationId,
      $equipmentIds,
    );

    $compliant = 0;
    $dueSoon = 0;
    foreach ($dueStatusesByEquipmentId as $dueStatus) {
      if ('up_to_date' === $dueStatus) {
        ++$compliant;
      } elseif ('due_soon' === $dueStatus) {
        ++$dueSoon;
      }
    }

    $openNonConformities = $this->nonConformityStatistics->countOpenNonConformities((string) $organizationId);

    return new GetEquipmentKpisResult(
      totalAssets: $totalAssets,
      compliant: $compliant,
      dueSoon: $dueSoon,
      openNonConformities: $openNonConformities,
    );
  }
  // #endregion
}
