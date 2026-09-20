<?php

declare(strict_types=1);

namespace Workload\Application\UseCase\Query\Planning\AssessWorkload;

/**
 * AssessWorkloadResult.
 *
 * @category Workload
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AssessWorkloadResult implements \Shared\Application\Message\ResultMessage
{
  /**
   * @since 1.0.0
   *
   * @param \Workload\Application\Contract\Planning\WorkloadAssessment $assessment daily impact presented for explicit confirmation
   */
  public function __construct(public \Workload\Application\Contract\Planning\WorkloadAssessment $assessment)
  {
  }
}
