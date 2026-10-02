<?php

declare(strict_types=1);

namespace Intervention\Domain\Event\Workflow;

/**
 * Class InterventionWorkflowNotificationRequestedEvent
 *
 * Captures a workflow consequence for delivery after the main transaction commits.
 *
 * @category Event
 */
final readonly class InterventionWorkflowNotificationRequestedEvent
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param string $kind assigned, changes_requested or submitted notification
   * @param string $interventionId owning intervention identifier
   * @param string $interventionName intervention title at the transition
   * @param ?string $memberId assignment or responsible member identifier
   * @param ?string $organizationId organization for reviewer resolution
   * @param ?string $actorUserId submitting actor to exclude from reviewers
   *
   * @return void
   */
  public function __construct(
    public string $kind,
    public string $interventionId,
    public string $interventionName,
    public ?string $memberId = null,
    public ?string $organizationId = null,
    public ?string $actorUserId = null,
  ) {
  }
  // #endregion
}
