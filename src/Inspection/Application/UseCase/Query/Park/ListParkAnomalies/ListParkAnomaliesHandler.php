<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\Park\ListParkAnomalies;

use Equipment\Application\Port\Inbound\EquipmentParkScopePort;
use Inspection\Application\Port\Outbound\{EquipmentNamingPort, ParkAnomalyGatewayPort};
use Inspection\Application\UseCase\Query\NonConformity\ListOrganizationNonConformities\OrganizationNonConformityResult;
use Inspection\Domain\ValueObject\InspectionOrganizationId;
use Shared\Application\Contract\Pagination\PaginatedResult;
use Shared\Application\Message\QueryHandler;
use Shared\Domain\Exception\InvalidValueException;

use function array_keys;

/**
 * UseCase ListParkAnomaliesHandler.
 *
 * Resolves equipment ownership before pagination and enriches one page with one naming lookup.
 *
 * @category UseCase
 */
final readonly class ListParkAnomaliesHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * @access public
   *
   * @param ParkAnomalyGatewayPort $anomalies Inspection-owned finding reads
   * @param EquipmentParkScopePort $equipmentScope published Parc scope resolver
   * @param EquipmentNamingPort $equipmentNaming batched serial-number read
   *
   * @return void
   */
  public function __construct(
    private ParkAnomalyGatewayPort $anomalies,
    private EquipmentParkScopePort $equipmentScope,
    private EquipmentNamingPort $equipmentNaming,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @access public
   *
   * @param ListParkAnomaliesQuery $query scope and pagination request
   *
   * @return PaginatedResult<OrganizationNonConformityResult> page from the complete resolved scope
   */
  public function __invoke(ListParkAnomaliesQuery $query): PaginatedResult
  {
    $organizationId = (string) InspectionOrganizationId::fromString($query->organizationId);
    if ($query->pagination->offset < 0 || $query->pagination->limit < 1 || $query->pagination->limit > 100) {
      throw InvalidValueException::because('Park anomaly pagination requires a non-negative offset and a limit from 1 to 100.');
    }
    $equipmentIds = $this->equipmentScope->filterIds(
      $organizationId,
      $this->anomalies->findCandidateEquipmentIds($organizationId),
      $query->family,
      $query->customerId,
      $query->facilityId,
      $query->includeDescendants,
    );
    $entries = $this->anomalies->list($organizationId, $equipmentIds, $query->pagination, $query->sorting);
    $total = $this->anomalies->count($organizationId, $equipmentIds);
    $pageIds = [];
    foreach ($entries as $entry) {
      $pageIds[$entry->equipmentId] = true;
    }
    $serialNumbers = [] === $pageIds ? [] : $this->equipmentNaming->findSerialNumbersByIds(array_keys($pageIds));
    $results = [];
    foreach ($entries as $entry) {
      $results[] = new OrganizationNonConformityResult(
        nonConformityId: $entry->id,
        inspectionId: $entry->inspectionId,
        description: $entry->description,
        severity: $entry->severity,
        status: $entry->status,
        dueAt: $entry->dueAt?->format('c'),
        resolvedAt: $entry->resolvedAt?->format('c'),
        notes: $entry->notes,
        createdAt: $entry->createdAt,
        updatedAt: $entry->updatedAt,
        equipmentId: $entry->equipmentId,
        equipmentSerialNumber: $serialNumbers[$entry->equipmentId] ?? null,
      );
    }

    return new PaginatedResult(
      items: $results,
      total: $total,
      limit: $query->pagination->limit,
      offset: $query->pagination->offset,
    );
  }
  // #endregion
}
