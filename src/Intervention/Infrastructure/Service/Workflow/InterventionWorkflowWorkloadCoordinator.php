<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Service\Workflow;

use Doctrine\ORM\EntityManagerInterface;
use Intervention\Application\Contract\Workflow\{
  InterventionWorkflowMutation
};
use Intervention\Domain\Exception\{
  InterventionNotFoundException
};
use Intervention\Infrastructure\Persistence\Doctrine\Record\{
  InterventionWorkItemRecord
};
use Intervention\Infrastructure\Persistence\Doctrine\Workflow\DoctrineInterventionWorkflowReader;
use Workload\Application\Contract\Planning\WorkloadPlanningSnapshot;
use Workload\Application\Port\Inbound\{WorkloadCoordinationPort, WorkloadPlanningPort};

use function array_key_exists;

/**
 * Locks affected work and checks overload consent around a workflow mutation.
 */
final readonly class InterventionWorkflowWorkloadCoordinator
{
  public function __construct(
    private EntityManagerInterface $entityManager,
    private WorkloadCoordinationPort $workloadCoordination,
    private WorkloadPlanningPort $workloadPlanning,
    private DoctrineInterventionWorkflowReader $reader,
    private InterventionWorkflowMutationSupport $support,
  ) {
  }

  /**
   * Coordinates affected members and captures their demand before an operational mutation.
   *
   * @since 1.1.0
   *
   * @param InterventionWorkflowMutation $mutation requested operational mutation, including any explicit overload consent
   *
   * @return ?WorkloadPlanningSnapshot Relevant demand captured before the mutation. Null means no existing demand needs coordination.
   */
  public function prepareWorkloadMutation(InterventionWorkflowMutation $mutation): ?WorkloadPlanningSnapshot
  {
    if ('change' === $mutation->resource || ('intervention' === $mutation->resource && 'create' === $mutation->action)) {
      return null;
    }
    $context = 'create' === $mutation->action
      ? $this->reader->interventionContext(InterventionWorkflowPayload::requiredString($mutation->payload, 'interventionId'))
      : $this->reader->resourceContext($mutation->resource, $mutation->id ?? '');
    if (null === $context) {
      throw InterventionNotFoundException::withId($mutation->id ?? 'unknown');
    }
    // Parent first, sorted members next, task rows last, shared with time writes.
    $parent = $this->support->intervention($context->interventionId);
    $this->entityManager->refresh($parent);
    $members = [];
    foreach ($this->entityManager->getRepository(InterventionWorkItemRecord::class)->findBy(['intervention' => $parent]) as $item) {
      $this->entityManager->refresh($item);
      if (null !== $item->assigneeId) {
        $members[] = $item->assigneeId;
      }
    }
    $nextAssignee = InterventionWorkflowPayload::nullableString($mutation->payload, 'assigneeId');
    if (null !== $nextAssignee) {
      $members[] = $nextAssignee;
    }
    $this->workloadCoordination->acquire($context->organizationId, $members);

    return $this->workloadPlanning->capture($context->organizationId, $members);
  }

  /**
   * Checks consent against demand captured before the mutation, while still inside the gateway transaction.
   */
  public function complete(?WorkloadPlanningSnapshot $before, InterventionWorkflowMutation $mutation): void
  {
    if (null !== $before && $this->requiresWorkloadAssessment($mutation)) {
      $this->workloadPlanning->assertAccepted(
        $before,
        InterventionWorkflowPayload::nullableString($mutation->payload, 'workloadConfirmationToken'),
      );
    }
  }

  /**
   * Identifies planning mutations that require a fresh overload assessment.
   *
   * @since 1.1.0
   *
   * @param InterventionWorkflowMutation $mutation requested operational mutation, including any explicit overload consent
   *
   * @return bool whether this mutation can introduce a planning overload
   */
  private function requiresWorkloadAssessment(InterventionWorkflowMutation $mutation): bool
  {
    if ('delete' === $mutation->action) {
      return false;
    }
    if ('create' === $mutation->action) {
      return true;
    }
    foreach (['status', 'assigneeId', 'workStartsOn', 'workEndsOn', 'plannedStartAt', 'dueAt'] as $field) {
      if (array_key_exists($field, $mutation->payload)) {
        return true;
      }
    }

    return false;
  }
}
