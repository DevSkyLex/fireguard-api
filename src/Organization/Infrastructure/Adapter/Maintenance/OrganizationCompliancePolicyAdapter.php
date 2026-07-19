<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Adapter\Maintenance;

use Maintenance\Application\Contract\Compliance\MaintenanceCompliancePolicy;
use Maintenance\Application\Port\Outbound\Compliance\MaintenanceCompliancePolicyPort;
use Organization\Application\Port\Outbound\OrganizationRepositoryPort;
use Organization\Domain\Catalog\OrganizationComplianceDefaults;
use Organization\Domain\ValueObject\OrganizationId;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Adapter OrganizationCompliancePolicyAdapter.
 *
 * Implements the Maintenance module's compliance policy port using the
 * organization's existing `OrganizationComplianceSettings` value object —
 * mirrors how `OrganizationNotificationPolicyService` resolves
 * `OrganizationNotificationPolicyPort` from the same settings aggregate.
 * Never throws on an unknown/malformed organization: falls back to "nothing
 * customized" (catalog defaults) so the recurring sweep can never crash on
 * one bad lookup.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationCompliancePolicyAdapter implements MaintenanceCompliancePolicyPort
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
  public function compliancePolicy(string $organizationId): MaintenanceCompliancePolicy
  {
    try {
      $organization = $this->organizationRepository->findById(OrganizationId::fromString($organizationId));
    } catch (InvalidValueException) {
      return new MaintenanceCompliancePolicy([], OrganizationComplianceDefaults::REMINDER_WINDOW_DAYS);
    }

    $settings = $organization?->settings()->compliance;
    if (null === $settings) {
      return new MaintenanceCompliancePolicy([], OrganizationComplianceDefaults::REMINDER_WINDOW_DAYS);
    }

    return new MaintenanceCompliancePolicy($settings->effectiveInspectionPeriodicities(), $settings->reminderWindowDays);
  }
  // #endregion
}
