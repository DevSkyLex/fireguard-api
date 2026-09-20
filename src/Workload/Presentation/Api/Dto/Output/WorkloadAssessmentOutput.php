<?php

declare(strict_types=1);

namespace Workload\Presentation\Api\Dto\Output;

/**
 * WorkloadAssessmentOutput.
 *
 * @category Workload
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class WorkloadAssessmentOutput
{
  /**
   * @since 1.0.0
   *
   * @param string $organizationId organization identifier that scopes this operation
   * @param \Workload\Application\Contract\Planning\WorkloadAssessment $assessment daily impact presented for explicit confirmation
   */
  public function __construct(#[\ApiPlatform\Metadata\ApiProperty(identifier: true)] public string $organizationId, public \Workload\Application\Contract\Planning\WorkloadAssessment $assessment)
  {
  }
}
