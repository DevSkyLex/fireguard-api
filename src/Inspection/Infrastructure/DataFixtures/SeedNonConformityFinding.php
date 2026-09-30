<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\DataFixtures;

use Inspection\Infrastructure\Persistence\Doctrine\Record\InspectionRecord;

/**
 * Identity and finding for one deterministic seeded non-conformity.
 *
 * @category DataFixtures
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SeedNonConformityFinding
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Provides finding details for a seeded non-conformity.
   *
   * @access public
   *
   * @param string $id seeded non-conformity identifier
   * @param InspectionRecord $inspection inspection record that owns the finding
   * @param string $description finding description
   * @param string $severity finding severity
   * @param string $status finding lifecycle status
   *
   * @return void
   */
  public function __construct(
    public string $id,
    public InspectionRecord $inspection,
    public string $description,
    public string $severity,
    public string $status,
  ) {
  }
  // #endregion
}
