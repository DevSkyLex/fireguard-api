<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Adapter\Approval;

use Approval\Application\Contract\Action\ApprovalActionTypes;
use Approval\Application\Contract\Policy\ApprovalPolicy;
use Approval\Application\Port\Outbound\ApprovalPolicyPort;
use Organization\Application\Port\Outbound\OrganizationRepositoryPort;
use Organization\Domain\Catalog\OrganizationApprovalDefaults;
use Organization\Domain\ValueObject\{OrganizationApprovalSettings, OrganizationId};
use Shared\Domain\Exception\InvalidValueException;

/**
 * Adapter OrganizationApprovalPolicyAdapter.
 *
 * Implements the Approval module's policy port using the organization's
 * existing `OrganizationApprovalSettings` value object — mirrors
 * {@see \Organization\Infrastructure\Adapter\Automation\OrganizationAutomationPolicyAdapter}.
 * Never throws on an unknown/malformed organization: falls back to
 * "everything disabled" so a lookup failure can never silently bypass or
 * silently trigger the approval gate.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationApprovalPolicyAdapter implements ApprovalPolicyPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param OrganizationRepositoryPort $organizationRepository the organization repository port
   */
  public function __construct(
    private OrganizationRepositoryPort $organizationRepository,
  ) {
  }
  // #endregion

  // #region Methods
  public function policyFor(string $organizationId): ApprovalPolicy
  {
    try {
      $organization = $this->organizationRepository->findById(OrganizationId::fromString($organizationId));
    } catch (InvalidValueException) {
      return self::disabledPolicy();
    }

    $settings = $organization?->settings()->approval;

    if (null === $settings) {
      return self::disabledPolicy();
    }

    return new ApprovalPolicy(
      actionRules: self::resolveActionRules($settings),
      allowSelfApproval: $settings->allowSelfApproval,
      approvalTtlDays: $settings->approvalTtlDays,
    );
  }

  /**
   * Method resolveActionRules.
   *
   * @static
   *
   * Resolves every known Approval action type's effective rule, applying
   * the `nc_waiver` default minimum severity when not customized — the only
   * place this Organization-hosted adapter knows about a specific Approval
   * action type value (the domain value object itself never does).
   *
   * @since 1.0.0
   *
   * @param OrganizationApprovalSettings $settings the organization approval settings
   *
   * @return array<string, array{enabled: bool, minApproverRole: string, minSeverity: ?string}> the resolved action rules
   */
  private static function resolveActionRules(OrganizationApprovalSettings $settings): array
  {
    $actionRules = [];

    foreach (ApprovalActionTypes::all() as $actionType) {
      $rule = $settings->ruleFor($actionType);
      $minSeverity = $rule['minSeverity'] ?? (ApprovalActionTypes::NC_WAIVER === $actionType ? OrganizationApprovalDefaults::NC_WAIVER_MIN_SEVERITY : null);

      $actionRules[$actionType] = [
        'enabled' => $rule['enabled'],
        'minApproverRole' => $rule['minApproverRole'],
        'minSeverity' => $minSeverity,
      ];
    }

    return $actionRules;
  }

  /**
   * Method disabledPolicy.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @return ApprovalPolicy the permissive-off fallback policy
   */
  private static function disabledPolicy(): ApprovalPolicy
  {
    $actionRules = [];

    foreach (ApprovalActionTypes::all() as $actionType) {
      $actionRules[$actionType] = [
        'enabled' => false,
        'minApproverRole' => OrganizationApprovalDefaults::MIN_APPROVER_ROLE,
        'minSeverity' => null,
      ];
    }

    return new ApprovalPolicy($actionRules, OrganizationApprovalDefaults::ALLOW_SELF_APPROVAL, OrganizationApprovalDefaults::APPROVAL_TTL_DAYS);
  }
  // #endregion
}
