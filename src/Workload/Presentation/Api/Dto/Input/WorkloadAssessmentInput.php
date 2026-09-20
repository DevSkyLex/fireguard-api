<?php

declare(strict_types=1);

namespace Workload\Presentation\Api\Dto\Input;

/**
 * WorkloadAssessmentInput.
 *
 * @category Workload
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class WorkloadAssessmentInput
{
  /**
   * @since 1.0.0
   *
   * @var list<WorkloadTaskProposalInput>
   */
  #[\Symfony\Component\Validator\Constraints\Valid]
  #[\Symfony\Component\Validator\Constraints\Count(max: 100)]
  public array $changes = [];

  /**
   * @since 1.0.0
   */
  #[\Symfony\Component\Validator\Constraints\Uuid]
  public ?string $planInterventionId = null;
}
