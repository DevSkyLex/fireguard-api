<?php

declare(strict_types=1);

namespace Inspection\Application\Contract\Inspection;

/**
 * Result, lifecycle status and performed-time bounds for inspection reads.
 */
final readonly class InspectionExecutionCriteria
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Groups result, status, and performed-time filters for inspection reads.
   *
   * @access public
   *
   * @param ?string $result optional inspection result filter
   * @param ?string $status optional inspection lifecycle status filter
   * @param ?string $performedAtFrom inclusive lower bound for performed time, when supplied
   * @param ?string $performedAtTo inclusive upper bound for performed time, when supplied
   *
   * @return void
   */
  public function __construct(
    public ?string $result = null,
    public ?string $status = null,
    public ?string $performedAtFrom = null,
    public ?string $performedAtTo = null,
  ) {
  }
  // #endregion
}
