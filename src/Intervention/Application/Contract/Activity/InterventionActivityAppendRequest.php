<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Activity;

/**
 * A system event or comment to append to an intervention activity feed.
 */
final readonly class InterventionActivityAppendRequest
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Identifies the intervention activity entry and its organization context.
   *
   * @access public
   *
   * @param string $interventionId intervention receiving the activity entry
   * @param string $organizationId organization that owns the intervention
   * @param ?string $actorId optional member who performed the action
   * @param InterventionActivityContent $content event or comment content to append
   * @param ?string $clientId optional client idempotency identifier
   *
   * @return void
   */
  public function __construct(
    public string $interventionId,
    public string $organizationId,
    public ?string $actorId,
    public InterventionActivityContent $content,
    public ?string $clientId = null,
  ) {
  }
  // #endregion
}
