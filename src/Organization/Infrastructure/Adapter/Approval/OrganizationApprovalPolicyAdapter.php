<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Adapter\Approval;

use Approval\Application\Contract\Policy\ApprovalPolicy;
use Approval\Application\Port\Outbound\{ApprovalActionCatalogPort, ApprovalPolicyPort};
use Organization\Application\Port\Outbound\Internal\OrganizationRepositoryPort;
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
   * @param ApprovalActionCatalogPort $catalog the installed action defaults
   */
  public function __construct(
    private OrganizationRepositoryPort $organizationRepository,
    private ApprovalActionCatalogPort $catalog,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method policyFor.
   *
   * Maps an organization's approval settings to the policy consumed by the Approval module.
   *
   * @access public
   *
   * @param string $organizationId the organization identifier
   *
   * @return ApprovalPolicy the effective approval policy, disabled when settings are unavailable
   */
  public function policyFor(string $organizationId): ApprovalPolicy
  {
    try {
      $organization = $this->organizationRepository->findById(OrganizationId::fromString($organizationId));
    } catch (InvalidValueException) {
      return $this->disabledPolicy();
    }

    $settings = $organization?->settings()->approval;

    if (null === $settings) {
      return $this->disabledPolicy();
    }

    return new ApprovalPolicy(
      actionRules: $this->resolveActionRules($settings),
      allowSelfApproval: $settings->allowSelfApproval,
      approvalTtlDays: $settings->approvalTtlDays,
    );
  }

  /**
   * Method resolveActionRules.
   *
   * Resolves installed action rules using owner-contributed severity defaults.
   * The Organization domain retains stored action keys without importing their owners.
   *
   * @since 1.0.0
   *
   * @param OrganizationApprovalSettings $settings the organization approval settings
   *
   * @return array<string, array{enabled: bool, minApproverRole: string, minSeverity: ?string}> the resolved action rules
   */
  private function resolveActionRules(OrganizationApprovalSettings $settings): array
  {
    $actionRules = [];

    foreach ($this->catalog->descriptors() as $descriptor) {
      $actionType = $descriptor->value;
      $rule = $settings->ruleFor($actionType);
      $minSeverity = $rule['minSeverity'] ?? $descriptor->defaultMinSeverity;

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
   * @since 1.0.0
   *
   * @return ApprovalPolicy the permissive-off fallback policy
   */
  private function disabledPolicy(): ApprovalPolicy
  {
    $actionRules = [];

    foreach ($this->catalog->descriptors() as $descriptor) {
      $actionRules[$descriptor->value] = [
        'enabled' => false,
        'minApproverRole' => OrganizationApprovalDefaults::MIN_APPROVER_ROLE,
        'minSeverity' => null,
      ];
    }

    return new ApprovalPolicy($actionRules, OrganizationApprovalDefaults::ALLOW_SELF_APPROVAL, OrganizationApprovalDefaults::APPROVAL_TTL_DAYS);
  }
  // #endregion
}
