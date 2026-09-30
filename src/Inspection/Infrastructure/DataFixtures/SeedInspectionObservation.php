<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\DataFixtures;

use DateTimeImmutable;

/**
 * Inspector, result and lifecycle for one deterministic seeded inspection.
 *
 * @category DataFixtures
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SeedInspectionObservation
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Provides inspector and lifecycle data for a seeded inspection.
   *
   * @access public
   *
   * @param string $inspectorType kind of inspector represented by the fixture
   * @param string $inspectorName display name of the inspector
   * @param string $result seeded inspection result
   * @param string $status seeded inspection lifecycle status
   * @param DateTimeImmutable $performedAt time the inspection was performed
   * @param ?string $inspectorUserId optional user identifier of the inspector
   * @param ?string $inspectorOrganizationName optional organization name of the inspector
   *
   * @return void
   */
  public function __construct(
    public string $inspectorType,
    public string $inspectorName,
    public string $result,
    public string $status,
    public DateTimeImmutable $performedAt,
    public ?string $inspectorUserId = null,
    public ?string $inspectorOrganizationName = null,
  ) {
  }
  // #endregion
}
