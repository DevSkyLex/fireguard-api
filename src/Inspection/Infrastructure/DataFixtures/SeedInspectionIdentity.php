<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\DataFixtures;

/**
 * Identifiers and location for one deterministic seeded inspection.
 *
 * @category DataFixtures
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SeedInspectionIdentity
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Provides identifiers and optional resource links for a seeded inspection.
   *
   * @access public
   *
   * @param string $id seeded inspection identifier
   * @param string $equipmentId equipment identifier linked to the inspection
   * @param ?string $facilityId optional facility identifier
   * @param ?string $checklistId optional checklist identifier
   *
   * @return void
   */
  public function __construct(
    public string $id,
    public string $equipmentId,
    public ?string $facilityId,
    public ?string $checklistId,
  ) {
  }
  // #endregion
}
