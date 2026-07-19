<?php

declare(strict_types=1);

namespace Billing\Application\Contract\Plan;

/**
 * DTO PlanSummary.
 *
 * Read model exposed outside the Billing module's counterpart, used to enrich
 * the subscription read model with the organization's currently assigned
 * plan's stable key and display name without depending on the Organization
 * module's Domain or Infrastructure layers.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PlanSummary
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the PlanSummary class.
   *
   * @since 1.0.0
   *
   * @param string $key the plan's stable machine key (e.g. free, pro, max)
   * @param string $name the plan's display name
   */
  public function __construct(
    public string $key,
    public string $name,
  ) {
  }
  // #endregion
}
