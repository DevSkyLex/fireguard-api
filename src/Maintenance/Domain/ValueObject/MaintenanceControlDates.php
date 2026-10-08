<?php

declare(strict_types=1);

namespace Maintenance\Domain\ValueObject;

use DateTimeImmutable;

/**
 * Class MaintenanceControlDates
 *
 * Projects active controls to their earliest due and latest completion dates.
 * Any unscheduled control keeps the combined projection without a due date.
 *
 * @category ValueObject
 */
final readonly class MaintenanceControlDates
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Starts an empty projection or retains a previous immutable accumulation.
   *
   * @access private
   *
   * @param bool $tracked whether an eligible control has been seen
   * @param ?DateTimeImmutable $nextDueAt the earliest date, absent if any date is missing
   * @param ?DateTimeImmutable $lastCompletedAt the latest validated control completion
   * @param bool $missingDueDate whether a control lacks a due date
   *
   * @return void
   */
  private function __construct(
    public bool $tracked = false,
    public ?DateTimeImmutable $nextDueAt = null,
    public ?DateTimeImmutable $lastCompletedAt = null,
    private bool $missingDueDate = false,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method empty
   *
   * Starts a projection with no eligible controls.
   *
   * @access public
   *
   * @return self the empty accumulation
   */
  public static function empty(): self
  {
    return new self();
  }

  /**
   * Method including
   *
   * Accumulates dates without allowing a later scheduled control to hide a missing due date.
   *
   * @access public
   *
   * @param ?DateTimeImmutable $nextDueAt the eligible control's current due date
   * @param ?DateTimeImmutable $lastCompletedAt the eligible control's last completion
   *
   * @return self the combined immutable dates
   */
  public function including(?DateTimeImmutable $nextDueAt, ?DateTimeImmutable $lastCompletedAt): self
  {
    $missingDueDate = $this->missingDueDate || null === $nextDueAt;
    $next = $this->nextDueAt;
    if (null !== $nextDueAt && (null === $next || $nextDueAt < $next)) {
      $next = $nextDueAt;
    }
    $last = $this->lastCompletedAt;
    if (null !== $lastCompletedAt && (null === $last || $lastCompletedAt > $last)) {
      $last = $lastCompletedAt;
    }

    return new self(true, $missingDueDate ? null : $next, $last, $missingDueDate);
  }
  // #endregion
}
