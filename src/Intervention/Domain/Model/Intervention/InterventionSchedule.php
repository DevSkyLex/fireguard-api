<?php

declare(strict_types=1);

namespace Intervention\Domain\Model\Intervention;

use DateTimeImmutable;
use Intervention\Domain\ValueObject\InterventionPriority;

/** Priority and operating window of an intervention. */
final readonly class InterventionSchedule
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Captures intervention priority and its optional operating window.
   *
   * @access public
   *
   * @param InterventionPriority $priority planning priority
   * @param ?DateTimeImmutable $plannedStartAt optional planned start timestamp
   * @param ?DateTimeImmutable $dueAt optional due timestamp
   *
   * @return void
   */
  public function __construct(
    public InterventionPriority $priority,
    public ?DateTimeImmutable $plannedStartAt,
    public ?DateTimeImmutable $dueAt,
  ) {
  }
  // #endregion
}
