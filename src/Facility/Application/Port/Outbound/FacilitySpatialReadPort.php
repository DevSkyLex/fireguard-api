<?php

declare(strict_types=1);

namespace Facility\Application\Port\Outbound;

use Facility\Application\Contract\Spatial\FacilitySpatialContext;

/**
 * Port FacilitySpatialReadPort.
 *
 * Loads bounded, organization-scoped spatial ancestry in batches.
 *
 * @category Port
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface FacilitySpatialReadPort
{
  // #region Methods
  /**
   * Method readContext.
   *
   * @param string $organizationId organization whose rows may enter the snapshot
   * @param list<string> $facilityIds current resources being projected
   * @param list<string> $attachmentIds original spatial references to inspect
   */
  public function readContext(string $organizationId, array $facilityIds, array $attachmentIds = []): FacilitySpatialContext;
  // #endregion
}
