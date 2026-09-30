<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\DataFixtures;

use DateTimeImmutable;

/** A deterministic intervention scenario for workload demonstration data. */
final readonly class InterventionWorkloadScenario
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Defines one deterministic intervention scenario for workload fixtures.
   *
   * @access public
   *
   * @param string $key stable scenario key used by fixture references
   * @param string $name scenario display name
   * @param string $status scenario lifecycle status
   * @param ?DateTimeImmutable $start optional local start of the scenario window
   * @param ?DateTimeImmutable $end optional local end of the scenario window
   *
   * @return void
   */
  public function __construct(
    public string $key,
    public string $name,
    public string $status,
    public ?DateTimeImmutable $start,
    public ?DateTimeImmutable $end,
  ) {
  }
  // #endregion
}
