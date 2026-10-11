<?php

declare(strict_types=1);

namespace Organization\Application\Port\Inbound;

/**
 * Port OrganizationOwnerReadPort.
 *
 * @category Port
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface OrganizationOwnerReadPort
{
  // #region Methods
  /**
   * Reads the current owner identifier without exporting an aggregate.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   *
   * @return ?string the owner identifier, or null for an absent organization
   */
  public function ownerUserId(string $organizationId): ?string;
  // #endregion
}
