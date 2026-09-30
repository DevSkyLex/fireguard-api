<?php

declare(strict_types=1);

namespace Intervention\Domain\Model\Intervention;

/** Text supplied when an intervention is created or restored. */
final readonly class InterventionContent
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Captures the name and optional description of an intervention.
   *
   * @access public
   *
   * @param string $name intervention name
   * @param ?string $description optional intervention description
   *
   * @return void
   */
  public function __construct(public string $name, public ?string $description = null)
  {
  }
  // #endregion
}
