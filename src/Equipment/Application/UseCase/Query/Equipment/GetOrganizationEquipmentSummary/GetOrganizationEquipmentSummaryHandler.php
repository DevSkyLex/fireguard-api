<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Query\Equipment\GetOrganizationEquipmentSummary;

use Equipment\Application\Contract\Equipment\EquipmentListCriteria;
use Equipment\Application\Port\Outbound\EquipmentRepositoryPort;
use Equipment\Application\Service\EquipmentSelectionScopeResolver;
use Equipment\Application\UseCase\Query\Equipment\GetFacilityEquipmentSummary\GetFacilityEquipmentSummaryResult;
use Equipment\Domain\ValueObject\EquipmentOrganizationId;
use Shared\Application\Message\QueryHandler;

use function array_sum;

/**
 * Organization parc summary within the caller's selected customer and family.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOrganizationEquipmentSummaryHandler implements QueryHandler
{
  /**
   * @since 1.0.0
   */
  public function __construct(private EquipmentRepositoryPort $equipment, private EquipmentSelectionScopeResolver $scopes)
  {
  }

  /**
   * @since 1.0.0
   */
  public function __invoke(GetOrganizationEquipmentSummaryQuery $query): GetFacilityEquipmentSummaryResult
  {
    $counts = $this->equipment->countByStatusForCriteria(EquipmentOrganizationId::fromString($query->organizationId), new EquipmentListCriteria(
      facilityIds: $this->scopes->customerFacilities($query->organizationId, $query->customerId, null),
      typeCodes: $this->scopes->typesForFamily($query->organizationId, $query->family),
    ));
    $byStatus = [
      'in_stock' => $counts['in_stock'] ?? 0, 'operational' => $counts['operational'] ?? 0,
      'under_maintenance' => $counts['under_maintenance'] ?? 0, 'decommissioned' => $counts['decommissioned'] ?? 0,
    ];

    return new GetFacilityEquipmentSummaryResult(null === $query->customerId ? 'organization' : 'customer', array_sum($byStatus), $byStatus, $byStatus['under_maintenance'] + $byStatus['decommissioned']);
  }
}
