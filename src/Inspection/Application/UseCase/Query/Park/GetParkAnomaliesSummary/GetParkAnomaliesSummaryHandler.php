<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\Park\GetParkAnomaliesSummary;

use Equipment\Application\Port\Inbound\EquipmentParkScopePort;
use Inspection\Application\Port\Outbound\ParkAnomalyGatewayPort;
use Inspection\Domain\ValueObject\InspectionOrganizationId;
use Shared\Application\Message\QueryHandler;

/**
 * UseCase GetParkAnomaliesSummaryHandler.
 *
 * Resolves and validates the public equipment scope even when no candidate finding exists.
 *
 * @category UseCase
 */
final readonly class GetParkAnomaliesSummaryHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * @access public
   *
   * @param ParkAnomalyGatewayPort $anomalies Inspection-owned finding reads
   * @param EquipmentParkScopePort $equipmentScope published Parc scope resolver
   *
   * @return void
   */
  public function __construct(
    private ParkAnomalyGatewayPort $anomalies,
    private EquipmentParkScopePort $equipmentScope,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @access public
   *
   * @param GetParkAnomaliesSummaryQuery $query Parc scope request
   *
   * @return GetParkAnomaliesSummaryResult complete unresolved counts
   */
  public function __invoke(GetParkAnomaliesSummaryQuery $query): GetParkAnomaliesSummaryResult
  {
    $organizationId = (string) InspectionOrganizationId::fromString($query->organizationId);
    $equipmentIds = $this->equipmentScope->filterIds(
      $organizationId,
      $this->anomalies->findCandidateEquipmentIds($organizationId),
      $query->family,
      $query->customerId,
      $query->facilityId,
      $query->includeDescendants,
    );
    $counts = $this->anomalies->summary($organizationId, $equipmentIds);

    return new GetParkAnomaliesSummaryResult($counts->openAnomalies, $counts->bySeverity);
  }
  // #endregion
}
