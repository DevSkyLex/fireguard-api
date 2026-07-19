<?php

declare(strict_types=1);

namespace Approval\Application\Port\Outbound;

use Approval\Application\Contract\Policy\ApprovalPolicy;

/**
 * Port ApprovalPolicyPort.
 *
 * Cross-module outbound port resolving an organization's effective approval
 * policy. Implemented by the Organization module (reads the organization's
 * own `OrganizationApprovalSettings`), mirroring
 * {@see \Automation\Application\Port\Outbound\AutomationPolicyPort}.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface ApprovalPolicyPort
{
  /**
   * Method policyFor.
   *
   * Resolves the approval policy for an organization. Implementations must
   * never throw on an unknown or malformed identifier: they fall back to
   * the permissive-off defaults (every action type disabled) so a lookup
   * failure can never silently bypass or silently trigger an approval gate.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   *
   * @return ApprovalPolicy the effective approval policy
   */
  public function policyFor(string $organizationId): ApprovalPolicy;
}
