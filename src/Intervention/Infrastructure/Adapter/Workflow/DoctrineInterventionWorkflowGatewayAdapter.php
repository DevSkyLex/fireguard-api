<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Adapter\Workflow;

use Doctrine\ORM\EntityManagerInterface;
use Intervention\Application\Contract\Export\InterventionExportCandidate;
use Intervention\Application\Contract\Workflow\{InterventionWorkflowContext, InterventionWorkflowMutation, InterventionWorkflowPage, InterventionWorkflowView};
use Intervention\Application\Port\Outbound\{InterventionIssueQueryPort, InterventionWorkflowGatewayPort};
use Intervention\Application\Service\InterventionIssueFinder;
use Intervention\Infrastructure\Persistence\Doctrine\Workflow\DoctrineInterventionWorkflowReader;
use Intervention\Infrastructure\Service\Workflow\{
  InterventionWorkflowChangeWriter,
  InterventionWorkflowInterventionWriter,
  InterventionWorkflowWorkItemWriter,
  InterventionWorkflowWorkloadCoordinator
};
use InvalidArgumentException;
use Shared\Application\Contract\Sorting\{SortDirection, Sorting};

/**
 * Keeps the workflow transaction and after-commit effects around focused read and write collaborators.
 */
final readonly class DoctrineInterventionWorkflowGatewayAdapter implements InterventionIssueQueryPort, InterventionWorkflowGatewayPort
{
  public function __construct(
    private EntityManagerInterface $entityManager,
    private DoctrineInterventionWorkflowReader $reader,
    private InterventionWorkflowWorkloadCoordinator $workload,
    private InterventionWorkflowInterventionWriter $interventions,
    private InterventionWorkflowWorkItemWriter $workItems,
    private InterventionWorkflowChangeWriter $changes,
    private InterventionIssueFinder $issueFinder,
  ) {
  }

  public function interventionContext(string $interventionId): ?InterventionWorkflowContext
  {
    return $this->reader->interventionContext($interventionId);
  }

  public function resourceContext(string $resource, string $id): ?InterventionWorkflowContext
  {
    return $this->reader->resourceContext($resource, $id);
  }

  public function mutate(InterventionWorkflowMutation $mutation): ?InterventionWorkflowView
  {
    /** @var list<callable(): void> $notifications */
    $notifications = [];
    $view = $this->entityManager->wrapInTransaction(
      function () use ($mutation, &$notifications): ?InterventionWorkflowView {
        $before = $this->workload->prepareWorkloadMutation($mutation);
        $view = match ($mutation->resource) {
          'intervention' => $this->interventions->mutateIntervention($mutation, $notifications),
          'work_item' => $this->workItems->mutateWorkItem($mutation, $notifications),
          'change' => $this->changes->mutateChange($mutation),
          default => throw new InvalidArgumentException('Unsupported intervention workflow resource.'),
        };
        $this->workload->complete($before, $mutation);

        return $view;
      },
    );
    foreach ($notifications as $notify) {
      $notify();
    }

    return $view;
  }

  public function get(string $resource, string $id): ?InterventionWorkflowView
  {
    return $this->reader->get($resource, $id);
  }

  /**
   * @param array<string, mixed> $filters
   */
  public function list(string $resource, string $scopeId, array $filters, int $page, int $itemsPerPage, Sorting $sorting = new Sorting('updatedAt', SortDirection::DESC)): InterventionWorkflowPage
  {
    return $this->reader->list($resource, $scopeId, $filters, $page, $itemsPerPage, $sorting);
  }

  /**
   * @param array<string, mixed> $filters
   */
  public function countInterventions(string $organizationId, array $filters): int
  {
    return $this->reader->countInterventions($organizationId, $filters);
  }

  /**
   * @param array<string, mixed> $filters
   *
   * @return list<InterventionExportCandidate>
   */
  public function listInterventionExportCandidates(string $organizationId, array $filters): array
  {
    return $this->reader->listInterventionExportCandidates($organizationId, $filters);
  }

  /**
   * @return list<\Intervention\Application\Contract\Resource\InterventionIssue>
   */
  public function issues(string $interventionId): array
  {
    return $this->issueFinder->find($interventionId);
  }
}
