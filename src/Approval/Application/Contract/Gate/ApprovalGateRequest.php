<?php

declare(strict_types=1);

namespace Approval\Application\Contract\Gate;

/**
 * Contract ApprovalGateRequest.
 *
 * Carries everything {@see \Approval\Application\Port\Inbound\ApprovalGatePort}
 * needs to decide whether a regulated action may apply immediately or must
 * be deferred behind a four-eyes approval request.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ApprovalGateRequest
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   * @param string $actionType the regulated action type (see {@see \Approval\Application\Contract\Action\ApprovalActionTypes})
   * @param string $subjectId the acted-upon subject identifier
   * @param string $requestedByUserId the requesting user identifier
   * @param array<string, mixed> $payload the deferred action's payload (the owning module's original command parameters)
   */
  public function __construct(
    public string $organizationId,
    public string $actionType,
    public string $subjectId,
    public string $requestedByUserId,
    public array $payload = [],
  ) {
  }
  // #endregion
}
