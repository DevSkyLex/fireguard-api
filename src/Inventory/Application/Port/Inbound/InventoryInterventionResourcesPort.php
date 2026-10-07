<?php

declare(strict_types=1);

namespace Inventory\Application\Port\Inbound;

use Inventory\Application\Contract\Stock\InventoryCostFact;

/** @category Port */
interface InventoryInterventionResourcesPort
{
  /**
   * @return list<string> stable read-side publication reasons, without acquiring a lock
   */
  public function publicationBlockers(string $organizationId, string $interventionId): array;

  /**
   * Acquires the shared intervention lock; unresolved declarations refuse publication. Caller owns main transaction.
   */
  public function assertReadyToPublish(string $organizationId, string $interventionId): void;

  /**
   * @return list<InventoryCostFact> immutable quantities and values; unknown amounts stay null
   */
  public function costFacts(string $organizationId, string $interventionId): array;

  /**
   * Method economicInterventionIds
   *
   * Callers authorize finance first. Reads only organization-owned material target identifiers, including pending declarations.
   *
   * @access public
   *
   * @param string $organizationId authorized owning organization
   * @param list<string> $equipmentIds at most 10000 candidate equipment identities
   *
   * @return list<string> at most 10000 distinct intervention identifiers; an oversized scope fails explicitly
   */
  public function economicInterventionIds(string $organizationId, array $equipmentIds): array;
}
