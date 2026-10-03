<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Query\Equipment\GetFacilityEquipmentSummary;

use Equipment\Application\Contract\Equipment\EquipmentListCriteria;
use Equipment\Application\Port\Outbound\{EquipmentRepositoryPort, FacilitySubtreeScopePort};
use Equipment\Domain\Exception\EquipmentNotFoundException;
use Equipment\Domain\ValueObject\{EquipmentFacilityId, EquipmentOrganizationId};
use Shared\Application\Message\QueryHandler;

use function array_sum;

/**
 * Handler GetFacilityEquipmentSummaryHandler.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetFacilityEquipmentSummaryHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * @since 1.0.0
   *
   * @param EquipmentRepositoryPort $equipment aggregates the same predicates as the published collection
   * @param FacilitySubtreeScopePort $facilities resolves organization-scoped published facility identifiers
   */
  public function __construct(private EquipmentRepositoryPort $equipment, private FacilitySubtreeScopePort $facilities)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param GetFacilityEquipmentSummaryQuery $query the requested facility scope
   *
   * @return GetFacilityEquipmentSummaryResult exact totals and all status buckets
   */
  public function __invoke(GetFacilityEquipmentSummaryQuery $query): GetFacilityEquipmentSummaryResult
  {
    $organizationId = EquipmentOrganizationId::fromString($query->organizationId);
    $facilityId = EquipmentFacilityId::fromString($query->facilityId);
    $facilityIds = $this->facilities->findPublishedSubtreeIds((string) $organizationId, (string) $facilityId);
    if ([] === $facilityIds) {
      throw EquipmentNotFoundException::forFacilityScope((string) $facilityId);
    }

    $counts = $this->equipment->countByStatusForCriteria($organizationId, new EquipmentListCriteria(
      facilityId: $query->includeDescendants ? null : (string) $facilityId,
      facilityIds: $query->includeDescendants ? $facilityIds : null,
    ));
    $byStatus = [
      'in_stock' => $counts['in_stock'] ?? 0,
      'operational' => $counts['operational'] ?? 0,
      'under_maintenance' => $counts['under_maintenance'] ?? 0,
      'decommissioned' => $counts['decommissioned'] ?? 0,
    ];

    return new GetFacilityEquipmentSummaryResult(
      $query->includeDescendants ? 'subtree' : 'direct',
      array_sum($byStatus),
      $byStatus,
      $byStatus['under_maintenance'] + $byStatus['decommissioned'],
    );
  }
  // #endregion
}
