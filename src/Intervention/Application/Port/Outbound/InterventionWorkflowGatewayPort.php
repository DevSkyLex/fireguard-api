<?php

declare(strict_types=1);

namespace Intervention\Application\Port\Outbound;

use Intervention\Application\Contract\Workflow\{
  InterventionWorkflowContext,
  InterventionWorkflowMutation,
  InterventionWorkflowPage,
  InterventionWorkflowView
};

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
   *
   * @return InterventionWorkflowPage the list result
   */
  public function list(string $resource, string $scopeId, array $filters, int $page, int $itemsPerPage): InterventionWorkflowPage;
}
