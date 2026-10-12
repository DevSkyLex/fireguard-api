<?php

declare(strict_types=1);

namespace Audit\Infrastructure\Adapter\Authorization;

use Authorization\Application\Contract\Catalog\PermissionDefinition;
use Authorization\Application\Port\Inbound\PermissionCatalogContributionPort;

/**
 * Class AuditPermissionContributionAdapter
 *
 * Publishes Audit-owned global permission vocabulary through Authorization's pure contract.
 *
 * @category Adapter
 */
final readonly class AuditPermissionContributionAdapter implements PermissionCatalogContributionPort
{
  // #region Methods
  /**
   * Method definitions
   *
   * Keeps persisted keys, descriptions and publication order stable.
   *
   * @access public
   *
   * @return list<PermissionDefinition> owner vocabulary
   */
  public static function definitions(): array
  {
    return [
      new PermissionDefinition('audit.read', 'View audit events', 41),
      new PermissionDefinition('audit.export', 'Export audit events', 42),
      new PermissionDefinition('audit.*', 'All audit operations', 43),
    ];
  }
  // #endregion
}
