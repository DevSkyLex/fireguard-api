<?php

declare(strict_types=1);

namespace Organization\Application\Port\Inbound;

use Organization\Application\Contract\Quota\OrganizationQuotaDefinition;

/**
 * Interface OrganizationQuotaCatalogPort
 *
 * Publishes registered quota definitions and rejects unresolved keys before mutations.
 *
 * @category Port
 */
interface OrganizationQuotaCatalogPort
{
  // #region Methods
  /**
   * Method definitions
   *
   * @access public
   *
   * @return list<OrganizationQuotaDefinition> deterministic registered definitions
   */
  public function definitions(): array;

  /**
   * Method get
   *
   * @access public
   *
   * @return OrganizationQuotaDefinition registered resource or exception
   */
  public function get(string $key): OrganizationQuotaDefinition;

  /**
   * Method usage
   *
   * @access public
   *
   * @return int occupied slots for a registered resource
   */
  public function usage(string $organizationId, string $key): int;

  /**
   * Method normalizeLimits
   *
   * @access public
   *
   * @param array<array-key, mixed> $limits submitted limits whose keys must be registered resource names
   *
   * @return array<string, int> known non-negative limits
   */
  public function normalizeLimits(array $limits): array;
  // #endregion
}
