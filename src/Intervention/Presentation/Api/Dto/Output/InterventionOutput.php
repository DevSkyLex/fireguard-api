<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;

/**
 * DTO InterventionOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InterventionOutput
{
  /**
   * Property id.
   *
   * @since 1.0.0
   */
  #[ApiProperty(identifier: true)]
  public string $id = '';

  /**
   * Property organization.
   *
   * @since 1.0.0
   */
  public string $organization = '';

  /**
   * Property number.
   *
   * @since 1.0.0
   */
  public int $number = 0;

  /**
   * Property type.
   *
   * @since 1.0.0
   */
  public string $type = 'site_setup';

  /**
   * Property name.
   *
   * @since 1.0.0
   */
  public string $name = '';

  /**
   * Property description.
   *
   * @since 1.0.0
   */
  public ?string $description = null;

  /**
   * Property status.
   *
   * @since 1.0.0
   */
  public string $status = 'draft';

  /**
   * Property site.
   *
   * @since 1.0.0
   */
  public ?string $site = null;

  /**
   * Property responsible.
   *
   * @since 1.0.0
   */
  public ?string $responsible = null;

  /**
   * Property participants.
   *
   * @since 1.0.0
   *
   * @var list<string>
   */
  public array $participants = [];

  /**
   * Property priority.
   *
   * @since 1.0.0
   */
  public string $priority = 'normal';

  /**
   * Property plannedStartAt.
   *
   * @since 1.0.0
   */
  public ?string $plannedStartAt = null;

  /**
   * Property dueAt.
   *
   * @since 1.0.0
   */
  public ?string $dueAt = null;

  /**
   * Property reviewNote.
   *
   * @since 1.0.0
   */
  public ?string $reviewNote = null;

  /**
   * Property revision.
   *
   * @since 1.0.0
   */
  public int $revision = 1;

  /**
   * Property allowedTransitions.
   *
   * Workflow-legal next statuses reachable from the current status, straight
   * from the domain `InterventionTransitionPolicy` (excludes `published`, which
   * is reached only through the publication flow). The client intersects this
   * with the current member's permissions to decide which actions to offer,
   * instead of duplicating the transition table.
   *
   * @since 1.0.0
   *
   * @var list<string>
   */
  public array $allowedTransitions = [];

  /**
   * Property allowedActions.
   *
   * The caller-specific action-capability surface, straight from
   * `InterventionActionPolicy` — the same policy `MutateInterventionWorkflowHandler`
   * consults to enforce a mutation, so the client never re-derives the
   * permission/status/identity matrix by hand. Populated on the item and
   * collection read paths and on every mutation response that returns the
   * refreshed intervention, so a client menu recomputed from a write's
   * response can never go stale; `null` only when a response carries no
   * view at all.
   *
   * @since 1.3.0
   */
  public ?InterventionAllowedActionsOutput $allowedActions = null;

  /**
   * Property facilitiesCount.
   *
   * @since 1.0.0
   */
  public int $facilitiesCount = 0;

  /**
   * Property equipmentCount.
   *
   * @since 1.0.0
   */
  public int $equipmentCount = 0;

  /**
   * Property inspectionsCount.
   *
   * @since 1.0.0
   */
  public int $inspectionsCount = 0;

  /**
   * Property blockersCount.
   *
   * @since 1.0.0
   */
  public int $blockersCount = 0;

  /**
   * Property workItemsCount.
   *
   * @since 1.0.0
   */
  public int $workItemsCount = 0;

  /**
   * Property completedWorkItemsCount.
   *
   * @since 1.0.0
   */
  public int $completedWorkItemsCount = 0;

  /**
   * Property proposedChangesCount.
   *
   * @since 1.0.0
   */
  public int $proposedChangesCount = 0;

  /**
   * Property commentsCount.
   *
   * @since 1.0.0
   */
  public int $commentsCount = 0;

  /**
   * Property hasSignature.
   *
   * Whether the intervention already carries a completion signature
   * attachment (Phase 5d.2). At most one exists per intervention — a
   * re-upload replaces it, it never flips back to false on its own.
   *
   * @since 1.2.0
   */
  public bool $hasSignature = false;

  /**
   * Property labels.
   *
   * Embedded label summaries (`{id, name, color}`) assigned to the
   * intervention, so the client can render chips without extra requests.
   * Manage the underlying labels through `/intervention-labels`.
   *
   * @since 1.0.0
   *
   * @var list<array{id: string, name: string, color: string}>
   */
  public array $labels = [];

  /**
   * Property createdAt.
   *
   * @since 1.0.0
   */
  public string $createdAt = '';

  /**
   * Property updatedAt.
   *
   * @since 1.0.0
   */
  public string $updatedAt = '';
}
