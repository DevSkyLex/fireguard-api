<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Resource;

/**
 * Resource InterventionResourceSummary.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionResourceSummary
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the InterventionResourceSummary class.
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
