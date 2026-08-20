<?php

declare(strict_types=1);

namespace Intervention\Application\Port\Outbound;

use Intervention\Application\Contract\Export\InterventionExportCandidate;
use Intervention\Application\Contract\Workflow\{
  InterventionWorkflowContext,
  InterventionWorkflowMutation,
  InterventionWorkflowPage,
  InterventionWorkflowView
};
use Shared\Application\Contract\Sorting\{SortDirection, Sorting};

/**
 * Interface InterventionWorkflowGatewayPort.
 *
 * @category Interface
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface InterventionWorkflowGatewayPort
{
  /**
   * Method interventionContext.
   *
   * Executes the intervention context operation.
   *
   * @since 1.0.0
   *
   * @param string $interventionId the intervention id value
   *
   * @return ?InterventionWorkflowContext the intervention context result
   */
  public function interventionContext(string $interventionId): ?InterventionWorkflowContext;

  /**
   * Method resourceContext.
   *
   * Executes the resource context operation.
   *
   * @since 1.0.0
   *
   * @param string $resource the resource value
   * @param string $id the id value
   *
   * @return ?InterventionWorkflowContext the resource context result
   */
  public function resourceContext(string $resource, string $id): ?InterventionWorkflowContext;

  /**
   * Method mutate.
   *
   * Executes the mutate operation.
   *
   * @since 1.0.0
   *
   * @param InterventionWorkflowMutation $mutation the mutation value
   *
   * @return ?InterventionWorkflowView the mutate result
   */
  public function mutate(InterventionWorkflowMutation $mutation): ?InterventionWorkflowView;

  /**
   * Method get.
   *
   * Executes the get operation.
   *
   * @since 1.0.0
   *
   * @param string $resource the resource value
   * @param string $id the id value
   *
   * @return ?InterventionWorkflowView the get result
   */
  public function get(string $resource, string $id): ?InterventionWorkflowView;

  /**
   * Method list.
   *
   * @since 1.0.0
   *
   * @param string $resource the resource value
   * @param string $scopeId the scope id value
   * @param array<string, mixed> $filters
   * @param int $page the page value
   * @param int $itemsPerPage the items per page value
   * @param Sorting $sorting the requested ordering
   *
   * @return InterventionWorkflowPage the list result
   */
  public function list(string $resource, string $scopeId, array $filters, int $page, int $itemsPerPage, Sorting $sorting = new Sorting('updatedAt', SortDirection::DESC)): InterventionWorkflowPage;

  /**
   * Method countInterventions.
   *
   * Counts interventions matching the given organization and filters —
   * exactly the same filter set {@see self::list()} applies for the
   * `intervention` resource — without fetching a single row. Backs the CSV
   * export's row cap, mirroring `AuditEventRepositoryPort::countMatching()`.
   *
   * @since 1.5.0
   *
   * @param string $organizationId the organization value
   * @param array<string, mixed> $filters
   *
   * @return int the matching intervention count
   */
  public function countInterventions(string $organizationId, array $filters): int;

  /**
   * Method listInterventionExportCandidates.
   *
   * Lists every intervention matching the given organization and filters, in
   * the CSV export's stable order (`updatedAt` DESC, `id` ASC), as
   * lightweight {@see InterventionExportCandidate} rows — never the heavier
   * {@see InterventionWorkflowView} the read endpoints use, which computes
   * per-row aggregate counts ({@see self::list()}'s `metrics`) the export
   * does not need. Callers must first bound the result with
   * {@see self::countInterventions()}.
   *
   * @since 1.5.0
   *
   * @param string $organizationId the organization value
   * @param array<string, mixed> $filters
   *
   * @return list<InterventionExportCandidate> the matching intervention rows
   */
  public function listInterventionExportCandidates(string $organizationId, array $filters): array;
}
