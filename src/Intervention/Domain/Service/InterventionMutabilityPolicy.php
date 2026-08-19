<?php

declare(strict_types=1);

namespace Intervention\Domain\Service;

use Intervention\Domain\ValueObject\InterventionStatus;

use function in_array;

/**
 * Service InterventionMutabilityPolicy.
 *
 * The field-mutability windows an {@see \Intervention\Domain\Model\Intervention\Intervention}
 * aggregate enforces on `edit()` (`assertScopeMutable()`, `assertOwnershipMutable()`,
 * `assertScheduleMutable()`), extracted as pure, queryable predicates so a
 * read-side caller (the action-capability advertisement on `InterventionOutput`)
 * can answer "would this field be editable right now?" without duplicating the
 * windows a second time. The aggregate delegates its own assertions to this
 * same class, so enforcement and advertisement provably share one source.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InterventionMutabilityPolicy
{
  // #region Methods
  /**
   * Method isMutable.
   *
   * Whether the intervention accepts any edit at all. `published` and
   * `abandoned` are terminal: every other window is a narrowing of this one.
   *
   * @since 1.0.0
   *
   * @param InterventionStatus $status the current status value
   *
   * @return bool true when the intervention is not terminal
   */
  public function isMutable(InterventionStatus $status): bool
  {
    return $status->isMutable();
  }

  /**
   * Method isScopeMutable.
   *
   * The site scopes every prepared work item, so it is editable while
   * drafting only — changing it later would invalidate the prepared scope;
   * recreating the intervention is the honest gesture.
   *
   * @since 1.0.0
   *
   * @param InterventionStatus $status the current status value
   *
   * @return bool true when the site is still editable
   */
  public function isScopeMutable(InterventionStatus $status): bool
  {
    return $this->isMutable($status) && InterventionStatus::DRAFT === $status;
  }

  /**
   * Method isOwnershipMutable.
   *
   * The responsible member governs submission, withdrawal and work-item
   * execution rights, so a handover is only allowed while nothing has
   * started: draft and planned.
   *
   * @since 1.0.0
   *
   * @param InterventionStatus $status the current status value
   *
   * @return bool true when the responsible member is still editable
   */
  public function isOwnershipMutable(InterventionStatus $status): bool
  {
    return $this->isMutable($status)
      && in_array($status, [InterventionStatus::DRAFT, InterventionStatus::PLANNED], true);
  }

  /**
   * Method isScheduleMutable.
   *
   * Dates, priority and participants stay editable through planned,
   * in_progress and changes_requested — a delayed intervention is
   * rescheduled, not abandoned and recreated. Under review (`submitted`)
   * everything is frozen: withdraw first.
   *
   * @since 1.0.0
   *
   * @param InterventionStatus $status the current status value
   *
   * @return bool true when the schedule fields are still editable
   */
  public function isScheduleMutable(InterventionStatus $status): bool
  {
    return $this->isMutable($status) && InterventionStatus::SUBMITTED !== $status;
  }
  // #endregion
}
