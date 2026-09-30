<?php

declare(strict_types=1);

namespace Intervention\Domain\Model\Intervention;

use DateTimeImmutable;
use Intervention\Domain\ValueObject\InterventionPriority;

/** Planning edits with explicit presence flags. */
final readonly class InterventionScheduleChanges
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Carries planning changes and flags that distinguish omission from null.
   *
   * @access public
   *
   * @param ?InterventionPriority $priority replacement priority, when supplied
   * @param ?DateTimeImmutable $plannedStartAt replacement planned start, when supplied
   * @param ?DateTimeImmutable $dueAt replacement due date, when supplied
   * @param bool $hasPriority whether priority was included in the patch
   * @param bool $hasPlannedStartAt whether planned start was included
   * @param bool $hasDueAt whether due date was included
   *
   * @return void
   */
  public function __construct(
    public ?InterventionPriority $priority = null,
    public ?DateTimeImmutable $plannedStartAt = null,
    public ?DateTimeImmutable $dueAt = null,
    public bool $hasPriority = false,
    public bool $hasPlannedStartAt = false,
    public bool $hasDueAt = false,
  ) {
  }
  // #endregion
}
