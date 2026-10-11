<?php

declare(strict_types=1);

namespace Facility\Application\Port\Outbound;

use Facility\Application\Contract\Facility\CanonicalFacilityPage;

/**
 * Port CanonicalFacilityCollectionPort.
 *
 * Reads bounded canonical pages without exposing persistence objects.
 *
 * @category Port
 */
interface CanonicalFacilityCollectionPort
{
  // #region Methods
  /**
   * Method page.
   */
  public function page(string $organizationId, ?string $interventionId, string $recordStatus, int $page, int $itemsPerPage): CanonicalFacilityPage;
  // #endregion
}
