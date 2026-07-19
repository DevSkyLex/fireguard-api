<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Adapter\Automation;

use Automation\Application\Contract\Policy\AutomationPolicy;
use Automation\Application\Port\Outbound\AutomationPolicyPort;
use Organization\Application\Port\Outbound\OrganizationRepositoryPort;
use Organization\Domain\Catalog\OrganizationComplianceDefaults;
use Organization\Domain\ValueObject\OrganizationId;
use Shared\Domain\Exception\InvalidValueException;

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
 *
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
  ) {
  }
  // #endregion

  // #region Methods
  public function policyFor(string $organizationId): AutomationPolicy
  {
    try {
      $organization = $this->organizationRepository->findById(OrganizationId::fromString($organizationId));
    } catch (InvalidValueException) {
      return new AutomationPolicy(false, OrganizationComplianceDefaults::NON_CONFORMITY_SLA_DAYS);
    }

    $settings = $organization?->settings();
    if (null === $settings) {
      return new AutomationPolicy(false, OrganizationComplianceDefaults::NON_CONFORMITY_SLA_DAYS);
    }

    return new AutomationPolicy(
      $settings->automation->autoCreateInterventionOnCriticalNc,
      $settings->compliance->effectiveNonConformitySlaDays(),
    );
  }
  // #endregion
}
