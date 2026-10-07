<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Draft;

/**
 * Class InterventionMaintenanceWork
 *
 * Identifies prepared legacy work for a safe preventive-engine handover.
 *
 * @category Contract
 */
final readonly class InterventionMaintenanceWork
{
  /**
   * Method __construct
   *
   * @access public
   *
   * @param string $interventionId owning intervention
   * @param int $number stable intervention number
   * @param string $workItemId prepared work identity
   *
   * @return void
   */
  public function __construct(public string $interventionId, public int $number, public string $workItemId)
  {
  }
}
