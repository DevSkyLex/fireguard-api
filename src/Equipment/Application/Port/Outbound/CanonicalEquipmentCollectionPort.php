<?php

declare(strict_types=1);

namespace Equipment\Application\Port\Outbound;

use Equipment\Application\Contract\Equipment\CanonicalEquipmentCollectionPage;

/**
 * Reads a scope-filtered canonical page.
 *
 * @category Port
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface CanonicalEquipmentCollectionPort
{
  // #region Methods
  /**
   * @since 1.0.0
   */
  public function read(string $organizationId, string $recordStatus, ?string $interventionId, ?string $facilityId, int $page, int $itemsPerPage): CanonicalEquipmentCollectionPage;
  // #endregion
}
