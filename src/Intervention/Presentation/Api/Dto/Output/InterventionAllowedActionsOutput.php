<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Dto\Output;

/**
 * DTO InterventionAllowedActionsOutput.
 *
 * The caller-specific action-capability surface embedded on
 * `InterventionOutput.allowedActions` — straight from
 * `Intervention\Application\Service\InterventionActionPolicy::allowedActions()`,
 * the same service `MutateInterventionWorkflowHandler` consults to enforce a
 * mutation. Every flag already folds together the organization permission,
 * the field-mutability window and the responsible/participant identity
 * check, so the client never re-derives the matrix (including the AND-rule
 * for a replan of a non-draft intervention) by hand.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InterventionAllowedActionsOutput
{
  /**
   * Property canEditDetails.
   *
   * Whether name, description and reviewNote are editable.
   *
   * @since 1.0.0
   */
  public bool $canEditDetails = false;

  /**
   * Property canEditSite.
   *
   * Whether the site is editable — draft only.
   *
   * @since 1.0.0
   */
  public bool $canEditSite = false;

  /**
   * Property canEditResponsible.
   *
   * Whether the responsible member is editable — draft and planned only.
   *
   * @since 1.0.0
   */
  public bool $canEditResponsible = false;

  /**
   * Property canEditPlanning.
   *
   * Whether participants, priority, plannedStartAt and dueAt are editable.
   *
   * @since 1.0.0
   */
  public bool $canEditPlanning = false;

  /**
   * Property canMutateWorkItems.
   *
   * Whether the caller may create, edit or delete work items.
   *
   * @since 1.0.0
   */
  public bool $canMutateWorkItems = false;

  /**
   * Property canMutateChanges.
   *
   * Whether the caller may propose a new change.
   *
   * @since 1.0.0
   */
  public bool $canMutateChanges = false;

  /**
   * Property canAssignTeam.
   *
   * Whether an organization team may be snapshot-assigned.
   *
   * @since 1.0.0
   */
  public bool $canAssignTeam = false;

  /**
   * Property canManageAttachments.
   *
   * Whether the caller may add or remove attachments.
   *
   * @since 1.0.0
   */
  public bool $canManageAttachments = false;

  /**
   * Property canSubmit.
   *
   * Whether the caller — the responsible member — may submit for review.
   *
   * @since 1.0.0
   */
  public bool $canSubmit = false;

  /**
   * Property canWithdraw.
   *
   * Whether the caller — the responsible member — may withdraw a submission.
   *
   * @since 1.0.0
   */
  public bool $canWithdraw = false;

  /**
   * Property canDelete.
   *
   * Whether the intervention may be deleted — draft or abandoned only.
   *
   * @since 1.0.0
   */
  public bool $canDelete = false;

  /**
   * Property canPublish.
   *
   * Whether a publication may be requested — submitted only.
   *
   * @since 1.0.0
   */
  public bool $canPublish = false;
}
