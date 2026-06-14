<?php

declare(strict_types=1);

namespace Mission\Application\Port\Outbound;

use Mission\Application\Contract\Workflow\{
  MissionWorkflowContext,
  MissionWorkflowMutation,
  MissionWorkflowPage,
  MissionWorkflowView
};

/**
 * Interface MissionWorkflowGatewayPort.
 *
 * @category Interface
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface MissionWorkflowGatewayPort
{
  /**
   * Method missionContext.
   *
   * Executes the mission context operation.
   *
   * @since 1.0.0
   *
   * @param string $missionId the mission id value
   *
   * @return ?MissionWorkflowContext the mission context result
   */
  public function missionContext(string $missionId): ?MissionWorkflowContext;

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
   * @return ?MissionWorkflowContext the resource context result
   */
  public function resourceContext(string $resource, string $id): ?MissionWorkflowContext;

  /**
   * Method mutate.
   *
   * Executes the mutate operation.
   *
   * @since 1.0.0
   *
   * @param MissionWorkflowMutation $mutation the mutation value
   *
   * @return ?MissionWorkflowView the mutate result
   */
  public function mutate(MissionWorkflowMutation $mutation): ?MissionWorkflowView;

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
   * @return ?MissionWorkflowView the get result
   */
  public function get(string $resource, string $id): ?MissionWorkflowView;

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
   * @return MissionWorkflowPage the list result
   */
  public function list(string $resource, string $scopeId, array $filters, int $page, int $itemsPerPage): MissionWorkflowPage;
}
