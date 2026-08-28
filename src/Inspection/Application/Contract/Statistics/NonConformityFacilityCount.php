<?php

declare(strict_types=1);

namespace Inspection\Application\Contract\Statistics;

/**
 * Contract NonConformityFacilityCount.
 *
 * Open non-conformity counts attributed to one facility, before name
 * resolution (the handler resolves names through the naming port).
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class NonConformityFacilityCount
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $facilityId the facility identifier
   * @param int $open the open (`open`/`in_progress`) non-conformity count
   * @param int $critical the open non-conformities with `critical` severity
   */
  public function __construct(
    public string $facilityId,
    public int $open,
    public int $critical,
  ) {
  }
  // #endregion
}
