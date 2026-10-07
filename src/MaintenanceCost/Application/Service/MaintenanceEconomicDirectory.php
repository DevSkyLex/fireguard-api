<?php

declare(strict_types=1);

namespace MaintenanceCost\Application\Service;

use Intervention\Application\Port\Outbound\{InterventionEconomicScopePort, InterventionEquipmentSnapshotPort};
use Inventory\Application\Port\Inbound\InventoryInterventionResourcesPort;
use MaintenanceCost\Application\Contract\Cost\MaintenanceCostView;
use MaintenanceCost\Application\Port\Inbound\MaintenanceCostReadPort;
use MaintenanceCost\Application\Port\Outbound\MaintenanceCostStorePort;
use MaintenanceCost\Domain\Exception\MaintenanceCostException;

use function array_keys;
use function array_unique;
use function array_values;
use function count;
use function in_array;

/**
 * Class MaintenanceEconomicDirectory
 *
 * Supplies verified private allocation identities before operational directory counting and pagination.
 *
 * @category Service
 */
final readonly class MaintenanceEconomicDirectory
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Uses owner-published bridges and the private financial store, with no sibling persistence access.
   *
   * @access public
   *
   * @param MaintenanceCostReadPort $costs authorized financial projections
   * @param MaintenanceCostStorePort $store captured allocation identities
   * @param InventoryInterventionResourcesPort $inventory direct material target references
   * @param InterventionEquipmentSnapshotPort $equipment current owned equipment scope
   * @param InterventionEconomicScopePort $scopes current owned facility scope
   *
   * @return void
   */
  public function __construct(private MaintenanceCostReadPort $costs, private MaintenanceCostStorePort $store, private InventoryInterventionResourcesPort $inventory, private InterventionEquipmentSnapshotPort $equipment, private InterventionEconomicScopePort $scopes)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method matchingIds
   *
   * Candidate references never establish a historic allocation. Only a matching current or captured allocation admits a source.
   *
   * @access public
   *
   * @param string $organizationId finance-authorized organization
   * @param ?string $siteId optional root site
   * @param ?string $customerId optional internal client
   * @param ?string $equipmentId optional asset
   *
   * @return list<string> bounded verified additional source identifiers
   */
  public function matchingIds(string $organizationId, ?string $siteId, ?string $customerId, ?string $equipmentId): array
  {
    if (null === $siteId && null === $customerId && null === $equipmentId) {
      return [];
    }
    $equipmentIds = null === $equipmentId ? [] : [$equipmentId];
    if (null !== $siteId || null !== $customerId) {
      $facilities = $this->scopes->facilityIds($organizationId, $siteId, $customerId);
      $scope = [] === $facilities ? [] : $this->equipment->equipmentIdsInFacilities($organizationId, $facilities);
      $equipmentIds = null === $equipmentId ? $scope : (in_array($equipmentId, $scope, true) ? [$equipmentId] : []);
    }
    $candidates = array_values(array_unique([...$this->store->economicInterventionIds($organizationId, $siteId, $customerId, $equipmentId), ...$this->inventory->economicInterventionIds($organizationId, $equipmentIds)]));
    if (count($candidates) > 10000) {
      throw MaintenanceCostException::invalid('The financial directory scope exceeds 10000 interventions; narrow its target filters.');
    }
    $matches = [];
    $factCount = 0;
    foreach ($candidates as $id) {
      $view = $this->costs->view($organizationId, $id);
      $this->assertScope($organizationId, $id, $view);
      $items = [...$view->current->items, ...($view->frozen?->totals->items ?? [])];
      $factCount += count($items);
      if ($factCount > 50000) {
        throw MaintenanceCostException::invalid('The financial directory scope exceeds 50000 contributions; narrow its target filters.');
      }
      foreach ($items as $item) {
        $allocation = $item->allocation;
        if (null === $allocation) {
          continue;
        }
        $matchesTarget = true;
        foreach (['site' => $siteId, 'customer' => $customerId, 'equipment' => $equipmentId] as $target => $identifier) {
          if (null !== $identifier && ($allocation[$target]['id'] ?? null) !== $identifier) {
            $matchesTarget = false;
          }
        }
        if ($matchesTarget) {
          $matches[$id] = true;

          break;
        }
      }
    }

    return array_keys($matches);
  }

  /**
   * Method equipment
   *
   * Transfers only minimal allocation labels; private amounts and source bodies stay inside the projection.
   *
   * @access public
   *
   * @param string $organizationId finance-authorized organization
   * @param string $interventionId selected scoped intervention
   *
   * @return list<array{id:string,name:?string,assetReference:?string,site:?array{id:string,name:string},customer:?array{id:string,name:string}}> minimal current and frozen equipment identities
   */
  public function equipment(string $organizationId, string $interventionId): array
  {
    $view = $this->costs->view($organizationId, $interventionId);
    $this->assertScope($organizationId, $interventionId, $view);
    $equipment = [];
    foreach ([...$view->current->items, ...($view->frozen?->totals->items ?? [])] as $item) {
      $allocation = $item->allocation;
      if (null !== $allocation && null !== $allocation['equipment']) {
        $identity = $allocation['equipment'];
        $key = $identity['id'] . ':' . ($allocation['site']['id'] ?? '') . ':' . ($allocation['customer']['id'] ?? '');
        $equipment[$key] ??= [...$identity, 'site' => $allocation['site'], 'customer' => $allocation['customer']];
      }
    }

    return array_values($equipment);
  }

  /**
   * Method assertScope
   *
   * Fails closed if a projection bridge returns another source identity.
   *
   * @access private
   *
   * @param string $organizationId authorized organization
   * @param string $interventionId requested source
   * @param MaintenanceCostView $view returned projection
   *
   * @return void
   */
  private function assertScope(string $organizationId, string $interventionId, MaintenanceCostView $view): void
  {
    if ($view->organizationId !== $organizationId || $view->interventionId !== $interventionId) {
      throw MaintenanceCostException::conflict('The financial directory source is outside the authorized organization.');
    }
  }
  // #endregion
}
