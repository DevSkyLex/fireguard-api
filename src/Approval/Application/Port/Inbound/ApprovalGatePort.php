<?php

declare(strict_types=1);

namespace Approval\Application\Port\Inbound;

use Approval\Application\Contract\Gate\{ApprovalGateDecision, ApprovalGateRequest};

/**
 * Port ApprovalGatePort.
 *
 * Inbound port the owning modules' presentation processors (Inspection,
 * Equipment) consult BEFORE applying a regulated action — mirrors the
 * `InterventionDraftFactoryPort` inbound-port precedent. Implementations
 * never expose Approval's Domain; the owning modules only ever see this
 * port and the {@see ApprovalGateRequest}/{@see ApprovalGateDecision}
 * contract types.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface ApprovalGatePort
{
  /**
   * Method evaluate.
   *
   * Evaluates whether a regulated action may apply immediately or must be
   * deferred behind a pending four-eyes approval request.
   *
   * @since 1.0.0
   *
   * @param ApprovalGateRequest $request the gate request
   *
   * @return ApprovalGateDecision the gate decision
   */
  public function evaluate(ApprovalGateRequest $request): ApprovalGateDecision;
}
