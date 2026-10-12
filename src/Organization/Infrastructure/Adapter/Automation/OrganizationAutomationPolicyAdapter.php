<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Adapter\Automation;

use Automation\Application\Contract\Policy\AutomationPolicy;
use Automation\Application\Port\Outbound\AutomationPolicyPort;
use Organization\Application\Port\Inbound\OrganizationSettingsPolicyCatalogPort;
use Organization\Application\Port\Outbound\Internal\OrganizationRepositoryPort;
use Organization\Domain\ValueObject\OrganizationId;
use Shared\Domain\Exception\InvalidValueException;

use function is_array;
use function is_int;

/**
 * Adapter OrganizationAutomationPolicyAdapter.
 *
 * Implements the Automation module's policy port using the organization's
 * existing `OrganizationAutomationSettings` (rule toggles) and
 * `OrganizationComplianceSettings` (non-conformity SLA per severity) value
 * objects — mirrors
 * {@see \Organization\Infrastructure\Adapter\Maintenance\OrganizationCompliancePolicyAdapter}.
 * Never throws on an unknown/malformed organization: falls back to
 * "everything off, catalog SLA defaults" so a lookup failure can never
 * silently trigger an automation.
 *
 * @category Adapter
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationAutomationPolicyAdapter implements AutomationPolicyPort
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
    private OrganizationSettingsPolicyCatalogPort $settingsPolicies,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method policyFor.
   *
   * Maps organization automation and compliance settings to the automation policy.
   *
   * @access public
   *
   * @param string $organizationId the organization identifier
   *
   * @return AutomationPolicy the effective policy, disabled when settings are unavailable
   */
  public function policyFor(string $organizationId): AutomationPolicy
  {
    try {
      $organization = $this->organizationRepository->findById(OrganizationId::fromString($organizationId));
    } catch (InvalidValueException) {
      return new AutomationPolicy(false, $this->defaultSlaDays());
    }

    $settings = $organization?->settings();
    if (null === $settings) {
      return new AutomationPolicy(false, $this->defaultSlaDays());
    }

    return new AutomationPolicy(
      $settings->automation->autoCreateInterventionOnCriticalNc,
      $settings->compliance->effectiveNonConformitySlaDays(),
    );
  }

  /**
   * @return array<string, int>
   */
  private function defaultSlaDays(): array
  {
    $value = $this->settingsPolicies->get('compliance.non_conformity_sla_days')->default;
    $defaults = [];
    if (is_array($value)) {
      foreach ($value as $key => $days) {
        if (is_int($days)) {
          $defaults[(string) $key] = $days;
        }
      }
    }

    return $defaults;
  }
  // #endregion
}
