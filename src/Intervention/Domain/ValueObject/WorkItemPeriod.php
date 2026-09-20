<?php

declare(strict_types=1);

namespace Intervention\Domain\ValueObject;

use DateTimeImmutable;
use Intervention\Domain\Exception\InterventionValidationException;

/**
 * Value object WorkItemPeriod, expressed as inclusive organization-local dates.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class WorkItemPeriod
{
  // #region Methods
  /**
   * @since 1.0.0
   *
   * @param ?string $startsOn inclusive organization-local start date
   * @param ?string $endsOn inclusive organization-local end date
   */
  public function __construct(public ?string $startsOn = null, public ?string $endsOn = null)
  {
    if ((null === $startsOn) !== (null === $endsOn)) {
      throw new InterventionValidationException('Both work period dates are required, or neither.');
    }
    foreach ([$startsOn, $endsOn] as $value) {
      if (null !== $value) {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (false === $date || $date->format('Y-m-d') !== $value) {
          throw new InterventionValidationException('Work dates must be valid YYYY-MM-DD local dates.');
        }
      }
    }
    if (null !== $startsOn && $startsOn > $endsOn) {
      throw new InterventionValidationException('The work period must end on or after its start.');
    }
  }

  /**
   * Checks that optional task dates remain inside the intervention planning window.
   *
   * @since 1.0.0
   *
   * @param ?string $startsOn inclusive organization-local start date
   * @param ?string $endsOn inclusive organization-local end date
   *
   * @return bool whether the optional task period fits inside the intervention period
   */
  public function fitsWithin(?string $startsOn, ?string $endsOn): bool
  {
    return null === $this->startsOn
      || (null !== $startsOn && null !== $endsOn && $this->startsOn >= $startsOn && $this->endsOn <= $endsOn);
  }
  // #endregion
}
