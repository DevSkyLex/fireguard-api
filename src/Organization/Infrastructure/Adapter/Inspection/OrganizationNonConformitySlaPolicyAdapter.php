<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Adapter\Inspection;

use Inspection\Application\Contract\Sla\NonConformitySlaPolicy;
use Inspection\Application\Port\Outbound\Compliance\NonConformitySlaPolicyPort;
use Organization\Application\Port\Outbound\OrganizationRepositoryPort;
use Organization\Domain\Catalog\OrganizationComplianceDefaults;
use Organization\Domain\ValueObject\OrganizationId;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Adapter OrganizationNonConformitySlaPolicyAdapter.
 *
 * Implements the Inspection module's non-conformity SLA policy port using
 * the organization's existing `OrganizationComplianceSettings` value object
 * — mirrors `OrganizationCompliancePolicyAdapter` (the Maintenance-facing
 * twin). Never throws on an unknown/malformed organization: falls back to
 * the catalog defaults so the recurring sweep can never crash on one bad
 * lookup.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationNonConformitySlaPolicyAdapter implements NonConformitySlaPolicyPort
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
  public function slaPolicy(string $organizationId): NonConformitySlaPolicy
  {
    try {
      $organization = $this->organizationRepository->findById(OrganizationId::fromString($organizationId));
    } catch (InvalidValueException) {
      return new NonConformitySlaPolicy(OrganizationComplianceDefaults::NON_CONFORMITY_SLA_DAYS);
    }

    $settings = $organization?->settings()->compliance;
    if (null === $settings) {
      return new NonConformitySlaPolicy(OrganizationComplianceDefaults::NON_CONFORMITY_SLA_DAYS);
    }

    return new NonConformitySlaPolicy($settings->effectiveNonConformitySlaDays());
  }
  // #endregion
}
