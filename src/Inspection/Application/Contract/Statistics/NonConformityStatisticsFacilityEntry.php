<?php

declare(strict_types=1);

namespace Inspection\Application\Contract\Statistics;

/**
 * Contract NonConformityStatisticsFacilityEntry.
 *
 * A `byFacility` statistics row after name resolution.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class NonConformityStatisticsFacilityEntry
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $facilityId the facility identifier
   * @param ?string $facilityName the facility display name (null when unresolvable)
   * @param int $open the open non-conformity count
   * @param int $critical the open non-conformities with `critical` severity
   */
  public function __construct(
    public string $facilityId,
    public ?string $facilityName,
    public int $open,
    public int $critical,
  ) {
  }
  // #endregion
}
