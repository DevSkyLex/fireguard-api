<?php

declare(strict_types=1);

namespace Approval\Application\Contract\Execution;

/**
 * Contract DeferredActionContext.
 *
 * Carries everything an {@see \Approval\Application\Port\Outbound\ApprovalActionExecutorPort}
 * adapter needs to re-dispatch the owning module's original command on
 * approval.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeferredActionContext
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   * @param string $actionType the regulated action type
   * @param string $subjectId the acted-upon subject identifier
   * @param array<string, mixed> $payload the deferred action's payload (the owning module's original command parameters)
   */
  public function __construct(
    public string $organizationId,
    public string $actionType,
    public string $subjectId,
    public array $payload,
  ) {
  }
  // #endregion
}
