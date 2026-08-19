<?php

declare(strict_types=1);

namespace Intervention\Application\Service;

/**
 * Service InterventionAllowedActions.
 *
 * The caller-specific action-capability surface computed by
 * {@see InterventionActionPolicy}: what THIS caller may do to THIS
 * intervention right now, folding together the organization permission
 * matrix, the field-mutability windows and the responsible/participant
 * identity checks {@see InterventionActionPolicy} itself enforces on write.
 * Mapped 1:1 onto `InterventionAllowedActionsOutput` by the Presentation
 * factory — kept as a distinct type per the naming convention that a
 * contract/service value never shares a name with a use case Result or an
 * Output DTO.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionAllowedActions
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param bool $canEditDetails name/description/reviewNote are editable
   * @param bool $canEditSite the site is editable (draft only)
   * @param bool $canEditResponsible the responsible member is editable
   * @param bool $canEditPlanning participants/priority/dates are editable
   * @param bool $canMutateWorkItems work items may be created/edited/deleted
   * @param bool $canMutateChanges a proposed change may be created
   * @param bool $canAssignTeam a team may be snapshot-assigned
   * @param bool $canManageAttachments attachments may be added or removed
   * @param bool $canSubmit the caller may submit for review
   * @param bool $canWithdraw the caller may withdraw a submission
   * @param bool $canDelete the intervention may be deleted
   * @param bool $canPublish a publication may be requested
   */
  public function __construct(
    public bool $canEditDetails,
    public bool $canEditSite,
    public bool $canEditResponsible,
    public bool $canEditPlanning,
    public bool $canMutateWorkItems,
    public bool $canMutateChanges,
    public bool $canAssignTeam,
    public bool $canManageAttachments,
    public bool $canSubmit,
    public bool $canWithdraw,
    public bool $canDelete,
    public bool $canPublish,
  ) {
  }
  // #endregion
}
