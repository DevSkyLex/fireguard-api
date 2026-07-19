<?php

declare(strict_types=1);

namespace Approval\Application\Contract\Policy;

/**
 * Contract ApprovalPolicy.
 *
 * An organization's effective, resolved approval policy — mirrors
 * {@see \Automation\Application\Contract\Policy\AutomationPolicy}. Resolved
 * by {@see \Approval\Application\Port\Outbound\ApprovalPolicyPort}
 * (implemented by the Organization module) from
 * `OrganizationApprovalSettings` overlaid on `OrganizationApprovalDefaults`.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ApprovalPolicy
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param array<string, array{enabled: bool, minApproverRole: string, minSeverity: ?string}> $actionRules the effective per-action-type rules
   * @param bool $allowSelfApproval whether the requester may also decide on their own request
   * @param int $approvalTtlDays the number of days a pending request stays valid before it expires
   */
  public function __construct(
    public array $actionRules,
    public bool $allowSelfApproval,
    public int $approvalTtlDays,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method isEnabledFor.
   *
   * @since 1.0.0
   *
   * @param string $actionType the action type value
   *
   * @return bool true when approval is required for this action type
   */
  public function isEnabledFor(string $actionType): bool
  {
    return (bool) ($this->actionRules[$actionType]['enabled'] ?? false);
  }

  /**
   * Method minApproverRoleFor.
   *
   * @since 1.0.0
   *
   * @param string $actionType the action type value
   *
   * @return string the minimum approver role required
   */
  public function minApproverRoleFor(string $actionType): string
  {
    $role = $this->actionRules[$actionType]['minApproverRole'] ?? 'admin';

    return '' === $role ? 'admin' : $role;
  }

  /**
   * Method minSeverityFor.
   *
   * @since 1.0.0
   *
   * @param string $actionType the action type value
   *
   * @return ?string the minimum non-conformity severity threshold, when applicable
   */
  public function minSeverityFor(string $actionType): ?string
  {
    return $this->actionRules[$actionType]['minSeverity'] ?? null;
  }
  // #endregion
}
