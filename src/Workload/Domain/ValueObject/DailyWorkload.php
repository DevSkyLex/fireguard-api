<?php

declare(strict_types=1);

namespace Workload\Domain\ValueObject;

use Shared\Domain\Exception\InvalidValueException;

use function max;

/**
 * Value object DailyWorkload.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DailyWorkload
{
  /**
   * @since 1.0.0
   *
   * @param ?int $capacityMinutes available minutes; null is unknown and zero means unavailable
   * @param int $actualMinutes recorded work, independent of remaining effort
   * @param int $remainingMinutes explicit remaining effort in whole minutes; never derived from actual time
   * @param int $draftMinutes tentative demand from draft interventions
   * @param bool $hasUnquantifiedWork whether unknown demand prevents a confident availability claim
   */
  public function __construct(
    public ?int $capacityMinutes,
    public int $actualMinutes,
    public int $remainingMinutes,
    public int $draftMinutes = 0,
    public bool $hasUnquantifiedWork = false,
  ) {
    if ((null !== $capacityMinutes && $capacityMinutes < 0) || $actualMinutes < 0 || $remainingMinutes < 0 || $draftMinutes < 0) {
      throw InvalidValueException::because('Daily workload cannot contain negative minutes.');
    }
  }

  /**
   * Computes daily committed excess, leaving unknown capacity unresolved.
   *
   * @since 1.0.0
   *
   * @return ?int committed minutes above capacity, or null when capacity is unknown
   */
  public function overloadMinutes(): ?int
  {
    return null === $this->capacityMinutes ? null : max(0, $this->actualMinutes + $this->remainingMinutes - $this->capacityMinutes);
  }

  /**
   * Computes daily utilization without dividing by zero or unknown capacity.
   *
   * @since 1.0.0
   *
   * @return ?float daily percentage, or null for unknown or zero capacity
   */
  public function utilizationPercent(): ?float
  {
    return null === $this->capacityMinutes || 0 === $this->capacityMinutes
      ? null
      : 100.0 * ($this->actualMinutes + $this->remainingMinutes) / $this->capacityMinutes;
  }

  /**
   * Distinguishes complete, partial, and unavailable workload calculations.
   *
   * @since 1.0.0
   *
   * @return string complete, partial, or unavailable calculation state
   */
  public function completeness(): string
  {
    return null === $this->capacityMinutes ? 'unavailable' : ($this->hasUnquantifiedWork ? 'partial' : 'complete');
  }

  /**
   * Classifies availability without presenting unknown work as spare capacity.
   *
   * @since 1.0.0
   *
   * @return string availability classification that preserves unknown work
   */
  public function availability(): string
  {
    if (0 === $this->capacityMinutes) {
      return 'unavailable';
    }
    if (null === $this->capacityMinutes || $this->hasUnquantifiedWork) {
      return 'unknown';
    }

    if (($this->overloadMinutes() ?? 0) > 0) {
      return 'overloaded';
    }

    return $this->actualMinutes + $this->remainingMinutes === $this->capacityMinutes ? 'fully_allocated' : 'available';
  }
}
