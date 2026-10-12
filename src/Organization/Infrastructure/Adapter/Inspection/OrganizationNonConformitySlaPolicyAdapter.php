<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Adapter\Inspection;

use Inspection\Application\Contract\Sla\NonConformitySlaPolicy;
use Inspection\Application\Port\Outbound\Compliance\NonConformitySlaPolicyPort;
use Organization\Application\Port\Inbound\OrganizationSettingsPolicyCatalogPort;
use Organization\Application\Port\Outbound\Internal\OrganizationRepositoryPort;
use Organization\Domain\ValueObject\OrganizationId;
use Shared\Domain\Exception\InvalidValueException;

use function is_array;
use function is_int;

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
    private OrganizationSettingsPolicyCatalogPort $settingsPolicies,
  ) {
  }

  // #endregion
  // #region Methods
  /**
   * Method slaPolicy
   *
   * Builds the effective non-conformity SLA policy from organization settings, using the module default when the organization or settings are unavailable.
   *
   * @access public
   *
   * @param string $organizationId the organization identifier
   *
   * @return NonConformitySlaPolicy
   */
  public function slaPolicy(string $organizationId): NonConformitySlaPolicy
  {
    try {
      $organization = $this->organizationRepository->findById(OrganizationId::fromString($organizationId));
    } catch (InvalidValueException) {
      return new NonConformitySlaPolicy($this->defaultSlaDays());
    }

    $settings = $organization?->settings()->compliance;
    if (null === $settings) {
      return new NonConformitySlaPolicy($this->defaultSlaDays());
    }

    return new NonConformitySlaPolicy($settings->effectiveNonConformitySlaDays());
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
