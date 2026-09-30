<?php

declare(strict_types=1);

namespace Organization\Domain\Model\Plan;

/**
 * Persisted plan catalog metadata, including the historical defaults.
 */
final readonly class RestoredPlanMetadata
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Carries persisted plan metadata and catalog ordering.
   *
   * @access public
   *
   * @param ?string $description optional plan description
   * @param bool $isActive whether the plan is active
   * @param bool $isDefault whether the plan is the default
   * @param int $sortOrder position used to order plans in the catalog
   *
   * @return void
   */
  public function __construct(
    public ?string $description = null,
    public bool $isActive = true,
    public bool $isDefault = false,
    public int $sortOrder = 0,
  ) {
  }
  // #endregion
}
