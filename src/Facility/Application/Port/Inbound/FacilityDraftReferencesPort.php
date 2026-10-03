<?php

declare(strict_types=1);

namespace Facility\Application\Port\Inbound;

/**
 * Port FacilityDraftReferencesPort.
 *
 * @category Port
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface FacilityDraftReferencesPort
{
  // #region Methods
  /**
   * @since 1.0.0
   *
   * @param string $interventionId the intervention being discarded
   *
   * @return list<string> its facility draft identifiers
   */
  public function draftIds(string $interventionId): array;
  // #endregion
}
