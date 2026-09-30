<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Recurrence;

use DateTimeImmutable;

/**
 * Rule overrides and their merge-patch presence flags.
 */
final readonly class InterventionRecurrenceCadencePatch
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Carries recurrence rule overrides with field-presence information.
   *
   * @access public
   *
   * @param ?string $frequency replacement recurrence frequency, when supplied
   * @param ?int $interval replacement interval between occurrences, when supplied
   * @param ?DateTimeImmutable $anchorDate replacement date anchoring the recurrence, when supplied
   * @param bool $hasFrequency whether frequency was included in the patch
   * @param bool $hasInterval whether interval was included in the patch
   * @param bool $hasAnchorDate whether anchor date was included in the patch
   *
   * @return void
   */
  public function __construct(
    public ?string $frequency,
    public ?int $interval,
    public ?DateTimeImmutable $anchorDate,
    public bool $hasFrequency,
    public bool $hasInterval,
    public bool $hasAnchorDate,
  ) {
  }
  // #endregion
}
