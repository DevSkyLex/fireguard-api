<?php

declare(strict_types=1);

namespace Equipment\Application\Port\Outbound;

/**
 * Port FacilityNamingPort.
 *
 * Resolves facility identifiers into display names for equipment listings.
 *
 * Separate from {@see FacilityValidationPort}: that one answers "may this
 * equipment be assigned here", a write-side rule that throws. This one answers
 * "what is this place called", a read-side lookup that never fails — folding
 * the two together would give a validation contract a reason to be called on
 * every list page.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface FacilityNamingPort
{
  // #region Methods
  /**
   * Method findNamesByIds.
   *
   * Resolves the display name of each given facility, in one round trip.
   *
   * Batched rather than per-equipment: the caller is a paginated listing, and
   * resolving one name per row is how a page of twenty becomes twenty queries.
   *
   * Unknown or inaccessible identifiers are absent from the result rather than
   * mapped to an empty string; callers render nothing for them, which is
   * honest — a name we could not resolve is not a blank name.
   *
   * @since 1.0.0
   *
   * @param list<string> $facilityIds the facility identifiers to resolve
   *
   * @return array<string, string> display name keyed by facility identifier
   */
  public function findNamesByIds(array $facilityIds): array;

  /**
   * Method findCodesByIds.
   *
   * Resolves the organization-scoped unique `code` of each given facility,
   * in one round trip — the export-side twin of {@see self::findNamesByIds()},
   * added for the equipment CSV export's reimport loop (the exported
   * `facilityCode` column is what `Import` reads back to reassign the item).
   *
   * A facility with no code, like an unknown identifier, is simply absent
   * from the result.
   *
   * @since 1.1.0
   *
   * @param list<string> $facilityIds the facility identifiers to resolve
   *
   * @return array<string, string> facility code keyed by facility identifier
   */
  public function findCodesByIds(array $facilityIds): array;
  // #endregion
}
