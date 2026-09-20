<?php

declare(strict_types=1);

namespace Workload\Application\Contract\Planning;

use RuntimeException;

/**
 * WorkloadConfirmationRequired.
 *
 * @category Workload
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class WorkloadConfirmationRequired extends RuntimeException
{
  /**
   * @since 1.0.0
   *
   * @param WorkloadAssessment $assessment daily impact presented for explicit confirmation
   */
  public function __construct(public readonly WorkloadAssessment $assessment)
  {
    parent::__construct('This change increases daily workload beyond availability. Confirm the current assessment.');
  }
}
