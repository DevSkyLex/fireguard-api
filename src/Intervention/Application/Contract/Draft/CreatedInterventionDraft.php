<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Draft;

/**
 * Contract CreatedInterventionDraft.
 *
 * Result of a programmatic intervention draft creation.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreatedInterventionDraft
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the CreatedInterventionDraft class.
   *
   * @since 1.0.0
   *
   * @param string $interventionId the created intervention identifier
   * @param int $number the per-organization sequential intervention number
   * @param int $workItemsCount the number of seeded work items
   */
  public function __construct(
    public string $interventionId,
    public int $number,
    public int $workItemsCount,
  ) {
  }
  // #endregion
}
