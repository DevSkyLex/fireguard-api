<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Draft;

/**
 * Contract InterventionDraftWorkItem.
 *
 * One planned work item to seed into a programmatically created intervention
 * draft.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionDraftWorkItem
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the InterventionDraftWorkItem class.
   *
   * @since 1.0.0
   *
   * @param string $action the work item action (e.g. "inspection", "create_equipment")
   * @param ?string $target the optional action target (serialized reference)
   * @param bool $required whether completing the item is required before submission
   * @param ?string $assigneeId the optional assigned member identifier
   * @param ?string $resultResource the optional expected result resource kind
   */
  public function __construct(
    public string $action,
    public ?string $target = null,
    public bool $required = true,
    public ?string $assigneeId = null,
    public ?string $resultResource = null,
  ) {
  }
  // #endregion
}
