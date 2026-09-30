<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Template;

/**
 * Optional site and responsible member copied into future interventions.
 */
final readonly class InterventionTemplateDefaults
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Captures default resource assignments for a template.
   *
   * @access public
   *
   * @param ?string $siteId optional site assigned to generated interventions
   * @param ?string $responsibleId optional responsible member assigned to generated interventions
   *
   * @return void
   */
  public function __construct(
    public ?string $siteId,
    public ?string $responsibleId,
  ) {
  }
  // #endregion
}
