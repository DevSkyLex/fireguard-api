<?php

declare(strict_types=1);

namespace Organization\Domain\Model\Plan;

/**
 * Optional catalog values supplied when creating a plan.
 *
 * @category Model
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PlanCreationOptions
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Captures optional plan metadata and catalog defaults for a new plan.
   *
   * @access public
   *
   * @param ?string $description optional plan description
   * @param bool $isActive whether the plan is available for assignment
   * @param bool $isDefault whether the plan is the catalog default
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
