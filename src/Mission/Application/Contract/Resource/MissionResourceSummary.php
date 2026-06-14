<?php

declare(strict_types=1);

namespace Mission\Application\Contract\Resource;

/**
 * Resource MissionResourceSummary.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MissionResourceSummary
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the MissionResourceSummary class.
   *
   * @since 1.0.0
   *
   * @param int $facilities the facilities value
   * @param int $equipment the equipment value
   * @param int $inspections the inspections value
   */
  public function __construct(
    public int $facilities,
    public int $equipment,
    public int $inspections,
  ) {
  }
}
