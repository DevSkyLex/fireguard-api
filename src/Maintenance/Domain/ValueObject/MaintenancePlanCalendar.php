<?php

declare(strict_types=1);

namespace Maintenance\Domain\ValueObject;

use DateTimeImmutable;
use Shared\Domain\Exception\InvalidValueException;

use function max;

/**
 * Class MaintenancePlanCalendar
 *
 * Keeps a plan's cadence, original anchor and current due slot consistent.
 * Historical sliding calendars retain their explicit mode and nullable dates.
 *
 * @category ValueObject
 */
final readonly class MaintenancePlanCalendar
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Restores dates in their already rehydrated calendar timezone without deriving
   * a replacement anchor from a clamped month-end due date.
   *
   * @access public
   *
   * @param PlanCadence $cadence the validated interval and calculation mode
   * @param ?DateTimeImmutable $anchorAt the original anchor, nullable for historical calendars
   * @param ?DateTimeImmutable $nextDueAt the current due slot, nullable for historical calendars
   * @param bool $legacy the explicit persisted historical arithmetic marker
   *
   * @return void
   */
  public function __construct(
    public PlanCadence $cadence,
    public ?DateTimeImmutable $anchorAt,
    public ?DateTimeImmutable $nextDueAt,
    public bool $legacy,
  ) {
    if ($legacy !== $cadence->legacy) {
      throw InvalidValueException::because('A fixed plan needs an anchor and next due date; its calculation mode must match its cadence.');
    }

    if ($legacy) {
      return;
    }
    if (null === $anchorAt || null === $nextDueAt) {
      throw InvalidValueException::because('A fixed plan needs an anchor and next due date; its calculation mode must match its cadence.');
    }

    if ($nextDueAt < $anchorAt) {
      throw InvalidValueException::because('A fixed plan cannot be due before its original anchor.');
    }

    if ($cadence->preview($anchorAt, $nextDueAt, 1)[0]->format('U.u') !== $nextDueAt->format('U.u')) {
      throw InvalidValueException::because('A fixed plan must be due on a calendar slot derived from its original anchor.');
    }
  }
  // #endregion

  // #region Methods
  /**
   * Method start
   *
   * Starts a calendar whose first due slot is its original anchor.
   *
   * @access public
   *
   * @param PlanCadence $cadence the validated interval
   * @param ?DateTimeImmutable $firstDueAt the first due date, nullable only for historical plans
   * @param bool $legacy whether historical sliding arithmetic applies
   *
   * @return self the initial calendar
   */
  public static function start(PlanCadence $cadence, ?DateTimeImmutable $firstDueAt, bool $legacy = false): self
  {
    return new self($cadence, $firstDueAt, $firstDueAt, $legacy);
  }

  /**
   * Method advance
   *
   * Advances an anchored slot past both its due date and validation instant,
   * while historical calendars continue sliding from validation.
   *
   * @access public
   *
   * @param DateTimeImmutable $validatedAt the validated completion instant
   *
   * @return self the advanced calendar with the original anchor
   */
  public function advance(DateTimeImmutable $validatedAt): self
  {
    if ($this->legacy) {
      return new self($this->cadence, $this->anchorAt, $this->cadence->addTo($validatedAt), true);
    }

    if (null === $this->anchorAt || null === $this->nextDueAt) {
      throw InvalidValueException::because('A fixed maintenance plan needs an explicit calendar.');
    }

    return new self($this->cadence, $this->anchorAt, $this->cadence->nextAfter($this->anchorAt, max($this->nextDueAt, $validatedAt)), false);
  }

  /**
   * Method preview
   *
   * Returns the upcoming slots without initializing an unscheduled historical calendar.
   *
   * @access public
   *
   * @param int $count the requested number of dates
   *
   * @return list<DateTimeImmutable> the upcoming calendar slots
   */
  public function preview(int $count = 3): array
  {
    if (null === $this->nextDueAt) {
      return [];
    }

    return $this->cadence->preview($this->anchorAt ?? $this->nextDueAt, $this->nextDueAt, $count);
  }
  // #endregion
}
