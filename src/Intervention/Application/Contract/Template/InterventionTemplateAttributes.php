<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Template;

/**
 * The validated descriptive fields of a new intervention template.
 */
final readonly class InterventionTemplateAttributes
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Captures the editable attributes shared by intervention templates.
   *
   * @access public
   *
   * @param string $name template display name
   * @param ?string $description optional explanatory text
   * @param string $type intervention type applied to generated work
   * @param string $priority default priority for generated interventions
   * @param ?string $duration optional ISO 8601 duration estimate
   *
   * @return void
   */
  public function __construct(
    public string $name,
    public ?string $description,
    public string $type,
    public string $priority,
    public ?string $duration,
  ) {
  }
  // #endregion
}
