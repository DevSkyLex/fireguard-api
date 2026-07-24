<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Resource;

/**
 * Resource InterventionWorkItemSummary.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionWorkItemSummary
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the InterventionWorkItemSummary class.
   *
   * @since 1.0.0
   *
   * @param int $total the total value
   * @param int $requiredIncomplete the required incomplete value
   * @param int $skipped the skipped value
   * @param int $discovered the discovered value
   * @param int $completed the completed or skipped value
   */
  public function __construct(
    public int $total,
    public int $requiredIncomplete,
    public int $skipped,
    public int $discovered,
    public int $completed = 0,
  ) {
  }
}
