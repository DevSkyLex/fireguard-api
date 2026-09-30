<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Recurrence;

use DateTimeImmutable;

/**
 * End date and activation overrides, with independent presence flags.
 */
final readonly class InterventionRecurrenceLifecyclePatch
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Carries recurrence end-date and activation overrides with presence flags.
   *
   * @access public
   *
   * @param ?DateTimeImmutable $endAt replacement end date, when supplied
   * @param ?bool $isActive replacement activation state, when supplied
   * @param bool $hasEndAt whether end date was included in the patch
   * @param bool $hasIsActive whether activation state was included in the patch
   *
   * @return void
   */
  public function __construct(
    public ?DateTimeImmutable $endAt,
    public ?bool $isActive,
    public bool $hasEndAt,
    public bool $hasIsActive,
  ) {
  }
  // #endregion
}
