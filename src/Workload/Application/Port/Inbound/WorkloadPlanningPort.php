<?php

declare(strict_types=1);

namespace Workload\Application\Port\Inbound;

/**
 * WorkloadPlanningPort.
 *
 * @category Workload
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface WorkloadPlanningPort
{
  /**
   * Captures the complete relevant demand before a coordinated planning mutation.
   *
   * @since 1.0.0
   *
   * @param string $organizationId organization identifier that scopes this operation
   * @param list<string> $memberIds
   *
   * @return \Workload\Application\Contract\Planning\WorkloadPlanningSnapshot relevant demand captured before the mutation
   */
  public function capture(string $organizationId, array $memberIds): \Workload\Application\Contract\Planning\WorkloadPlanningSnapshot;

  /**
   * Compares daily overload after replacing the proposed task contributions.
   *
   * @since 1.0.0
   *
   * @param \Workload\Application\Contract\Planning\WorkloadPlanningSnapshot $before snapshot captured before the proposed planning mutation
   * @param list<\Intervention\Application\Contract\Workload\InterventionWorkContribution> $replacements
   *
   * @return \Workload\Application\Contract\Planning\WorkloadAssessment daily before/after overload and assessment-bound consent token
   */
  public function assess(\Workload\Application\Contract\Planning\WorkloadPlanningSnapshot $before, array $replacements = []): \Workload\Application\Contract\Planning\WorkloadAssessment;

  /**
   * Rejects new or increased overload unless consent matches the current assessment.
   *
   * @since 1.0.0
   *
   * @param \Workload\Application\Contract\Planning\WorkloadPlanningSnapshot $before snapshot captured before the proposed planning mutation
   * @param ?string $confirmationToken consent bound to the exact server assessment, never blanket approval
   *
   * @return void completes without returning a value
   */
  public function assertAccepted(\Workload\Application\Contract\Planning\WorkloadPlanningSnapshot $before, ?string $confirmationToken): void;
}
