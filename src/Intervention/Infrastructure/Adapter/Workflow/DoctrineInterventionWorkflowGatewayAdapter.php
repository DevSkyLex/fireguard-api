<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Adapter\Workflow;

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
 * Class DoctrineInterventionWorkflowGatewayAdapter
 *
 * Coordinates workflow reads and transaction-scoped mutations across focused collaborators.
 *
 * @category Adapter
 */
final readonly class DoctrineInterventionWorkflowGatewayAdapter implements InterventionIssueQueryPort, InterventionWorkflowGatewayPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Supplies transaction, workflow read/write, workload and issue-finding collaborators.
   *
   * @access public
   *
   * @param DoctrineInterventionWorkflowReader $reader reads workflow state
   * @param InterventionWorkflowWorkloadCoordinator $workload coordinates workload changes
   * @param InterventionWorkflowInterventionWriter $interventions writes intervention mutations
   * @param InterventionWorkflowWorkItemWriter $workItems writes work-item mutations
   * @param InterventionWorkflowChangeWriter $changes writes proposed-change mutations
   * @param InterventionIssueFinder $issueFinder computes workflow issues
   * @param InterventionTransactionManagerAdapter $transactions owns mutation and outbox writes on main
   *
   * @return void
   */
  public function __construct(
    private DoctrineInterventionWorkflowReader $reader,
    private InterventionWorkflowWorkloadCoordinator $workload,
    private InterventionWorkflowInterventionWriter $interventions,
    private InterventionWorkflowWorkItemWriter $workItems,
    private InterventionWorkflowChangeWriter $changes,
    private InterventionIssueFinder $issueFinder,
    private InterventionTransactionManagerAdapter $transactions,
  ) {
  }

  // #endregion

  // #region Methods
  /**
   * Method interventionContext
   *
   * Reads the workflow context for an intervention identifier.
   *
   * @access public
   *
   * @param string $interventionId intervention identifier to resolve
   *
   * @return InterventionWorkflowContext|null context when the intervention exists
   */
  public function interventionContext(string $interventionId): ?InterventionWorkflowContext
  {
    return $this->reader->interventionContext($interventionId);
  }

  /**
   * Method resourceContext
   *
   * Reads the workflow context owning a typed resource identifier.
   *
   * @access public
   *
   * @param string $resource workflow resource type
   * @param string $id resource identifier
   *
   * @return InterventionWorkflowContext|null context when the resource exists
   */
  public function resourceContext(string $resource, string $id): ?InterventionWorkflowContext
  {
    return $this->reader->resourceContext($resource, $id);
  }

  /**
   * Method mutate
   *
   * Coordinates workload and resource mutations in one transaction, then persists outbox effects before commit.
   *
   * @access public
   *
   * @param InterventionWorkflowMutation $mutation requested workflow change
   *
   * @return InterventionWorkflowView|null updated view, or null for a deletion
   *
   * @throws InvalidArgumentException when the mutation names an unsupported resource
   */
  public function mutate(InterventionWorkflowMutation $mutation): ?InterventionWorkflowView
  {
    /** @var list<callable(): void> $notifications */
    $notifications = [];
    $view = $this->transactions->transactional(
      function () use ($mutation, &$notifications): ?InterventionWorkflowView {
        $before = $this->workload->prepareWorkloadMutation($mutation);
        $view = match ($mutation->resource) {
          'intervention' => $this->interventions->mutateIntervention($mutation, $notifications),
          'work_item' => $this->workItems->mutateWorkItem($mutation, $notifications),
          'change' => $this->changes->mutateChange($mutation),
          default => throw new InvalidArgumentException('Unsupported intervention workflow resource.'),
        };
        $this->workload->complete($before, $mutation);
        foreach ($notifications as $notify) {
          $notify();
        }

        return $view;
      },
    );

    return $view;
  }

  /**
   * Method get
   *
   * Reads one workflow view by resource type and identifier.
   *
   * @access public
   *
   * @param string $resource workflow resource type
   * @param string $id resource identifier
   *
   * @return InterventionWorkflowView|null view when the resource exists
   */
  public function get(string $resource, string $id): ?InterventionWorkflowView
  {
    return $this->reader->get($resource, $id);
  }

  /**
   * Method list
   *
   * Reads one page of workflow resources for a scope and filter set.
   *
   * @access public
   *
   * @param string $resource workflow resource type
   * @param string $scopeId organization or parent resource identifier
   * @param array<string, mixed> $filters criteria applied to the selected scope
   * @param int $page one-based page number
   * @param int $itemsPerPage requested page size
   * @param Sorting $sorting result ordering
   *
   * @return InterventionWorkflowPage requested resource page
   */
  public function list(string $resource, string $scopeId, array $filters, int $page, int $itemsPerPage, Sorting $sorting = new Sorting('updatedAt', SortDirection::DESC)): InterventionWorkflowPage
  {
    return $this->reader->list($resource, $scopeId, $filters, $page, $itemsPerPage, $sorting);
  }

  /**
   * Method countInterventions
   *
   * Counts organization interventions matching the supplied filters.
   *
   * @access public
   *
   * @param string $organizationId organization whose interventions are counted
   * @param array<string, mixed> $filters criteria applied to the count
   *
   * @return int matching intervention count
   */
  public function countInterventions(string $organizationId, array $filters): int
  {
    return $this->reader->countInterventions($organizationId, $filters);
  }

  /**
   * Method listInterventionExportCandidates
   *
   * Returns lightweight rows for a bounded intervention export.
   *
   * @access public
   *
   * @param string $organizationId organization whose interventions are exported
   * @param array<string, mixed> $filters criteria applied to the export
   *
   * @return list<InterventionExportCandidate> matching export rows
   */
  public function listInterventionExportCandidates(string $organizationId, array $filters): array
  {
    return $this->reader->listInterventionExportCandidates($organizationId, $filters);
  }

  /**
   * Method issues
   *
   * Finds computed validation issues for an intervention.
   *
   * @access public
   *
   * @param string $interventionId intervention whose issues are requested
   *
   * @return list<\Intervention\Application\Contract\Resource\InterventionIssue> computed intervention issues
   */
  public function issues(string $interventionId): array
  {
    return $this->issueFinder->find($interventionId);
  }
  // #endregion
}
