<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\DataFixtures;

use DateTimeImmutable;

/** A deterministic task row within a workload demonstration scenario. */
final readonly class InterventionWorkloadTask
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Defines one deterministic task within workload demonstration data.
   *
   * @access public
   *
   * @param string $key stable task key used by fixture references
   * @param string $label task display label
   * @param ?string $memberId optional assigned organization member identifier
   * @param ?int $minutes optional estimated effort in minutes
   * @param ?DateTimeImmutable $start optional start of the planned work period
   * @param ?DateTimeImmutable $end optional end of the planned work period
   *
   * @return void
   */
  public function __construct(
    public string $key,
    public string $label,
    public ?string $memberId,
    public ?int $minutes,
    public ?DateTimeImmutable $start,
    public ?DateTimeImmutable $end,
  ) {
  }
  // #endregion
}
