<?php

declare(strict_types=1);

namespace Intervention\Application\Port\Outbound;

use Intervention\Application\Contract\Workflow\{InterventionWorkflowPage, InterventionWorkflowView};

/**
 * Interface InterventionActivityPort.
 *
 * Appends and reads the intervention activity feed (member comments and
 * system-recorded lifecycle events). Shared by the comment use case and the
 * workflow gateway, which appends system events inside its own mutation
 * transaction.
 *
 * @category Interface
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface InterventionActivityPort
{
  /**
   * Method append.
   *
   * Appends a single activity to an intervention's feed. When called from
   * inside the workflow gateway's mutation transaction the write joins that
   * transaction, so a rollback also discards the activity.
   *
   * @since 1.0.0
   *
   * @param string $interventionId the intervention id value
   * @param string $organizationId the owning organization id value
   * @param ?string $actorId the acting organization member id, or null for pure-system activities
   * @param string $kind the activity kind (`comment` or `system`)
   * @param string $event the activity event (`comment`, `created`, or `status_changed`)
   * @param ?string $body the comment body, or null for system events
   * @param ?array<string, mixed> $payload structured event data, or null
   *
   * @return InterventionWorkflowView the appended activity view
   */
  public function append(
    string $interventionId,
    string $organizationId,
    ?string $actorId,
    string $kind,
    string $event,
    ?string $body,
    ?array $payload,
    ?string $clientId = null,
  ): InterventionWorkflowView;

  /**
   * Method listByIntervention.
   *
   * Lists an intervention's activity feed, oldest first.
   *
   * @since 1.0.0
   *
   * @param string $interventionId the intervention id value
   * @param int $page the page value
   * @param int $itemsPerPage the items per page value
   *
   * @return InterventionWorkflowPage the activity page result
   */
  public function listByIntervention(string $interventionId, int $page, int $itemsPerPage): InterventionWorkflowPage;
}
