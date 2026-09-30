<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Template;

/**
 * Priority and duration overrides with independent presence flags.
 */
final readonly class InterventionTemplatePlanningPatch
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Carries template planning overrides with independent presence flags.
   *
   * @access public
   *
   * @param ?string $priority replacement default priority, when supplied
   * @param ?string $duration replacement duration estimate, when supplied
   * @param bool $hasPriority whether priority was included in the patch
   * @param bool $hasDuration whether duration was included in the patch
   *
   * @return void
   */
  public function __construct(
    public ?string $priority,
    public ?string $duration,
    public bool $hasPriority,
    public bool $hasDuration,
  ) {
  }
  // #endregion
}
