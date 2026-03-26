<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\GetOrganizationEquipmentStatistics;

use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\{EquipmentStatisticsPort, OrganizationRepositoryPort};
use Organization\Domain\Exception\{OrganizationAccessDeniedException, OrganizationNotFoundException};
use Organization\Domain\ValueObject\OrganizationId;
use Shared\Application\Message\QueryHandler;

/**
 * UseCase GetOrganizationEquipmentStatisticsHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOrganizationEquipmentStatisticsHandler implements QueryHandler
{
  public function __construct(
    private OrganizationAuthorizationPort $authorization,
    private OrganizationRepositoryPort $organizationRepository,
    private EquipmentStatisticsPort $equipmentStatistics,
  ) {
  }

  public function __invoke(GetOrganizationEquipmentStatisticsQuery $query): GetOrganizationEquipmentStatisticsResult
  {
    $organizationId = OrganizationId::fromString($query->organizationId);
    $organization = $this->organizationRepository->findById($organizationId);

    if (null === $organization) {
      throw OrganizationNotFoundException::withId($query->organizationId);
    }

    if (!$this->authorization->hasPermission($query->userId, $query->organizationId, 'organization.equipment.read')) {
      throw OrganizationAccessDeniedException::missingPermission('organization.equipment.read');
    }

    $totalCount = $this->equipmentStatistics->countEquipment($query->organizationId);
    $countsByStatus = $this->equipmentStatistics->countEquipmentByStatus($query->organizationId);

    return new GetOrganizationEquipmentStatisticsResult(
      totalCount: $totalCount,
      inStockCount: $countsByStatus['in_stock'] ?? 0,
      operationalCount: $countsByStatus['operational'] ?? 0,
      underMaintenanceCount: $countsByStatus['under_maintenance'] ?? 0,
      decommissionedCount: $countsByStatus['decommissioned'] ?? 0,
      countsByType: $this->equipmentStatistics->countEquipmentByType($query->organizationId),
    );
  }
}
