<?php

declare(strict_types=1);

namespace Organization\Application\Port\Outbound;

use Organization\Application\Contract\Quota\OrganizationQuotaDefinition;

/**
 * Interface OrganizationQuotaContributionPort
 *
 * A resource owner publishes pure metadata and organization-scoped usage. Definitions must not resolve services.
 *
 * @category Port
 */
interface OrganizationQuotaContributionPort
{
  // #region Methods
  /**
   * Method definitions
   *
   * @access public
   *
   * @return list<OrganizationQuotaDefinition> owned quota definitions, safe during container compilation
   */
  public static function definitions(): array;

  /**
   * Method usage
   *
   * @access public
   *
   * @param string $organizationId scoped organization identifier
   * @param string $resourceKey one of this contributor's published keys
   *
   * @return int current occupied slots; no writes or locks
   */
  public function usage(string $organizationId, string $resourceKey): int;
  // #endregion
}
