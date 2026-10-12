<?php

declare(strict_types=1);

namespace Organization\Application\Port\Inbound;

use Organization\Application\Contract\Settings\OrganizationSettingsPolicyDefinition;

/**
 * Interface OrganizationSettingsPolicyCatalogPort
 *
 * Applies registered defaults and validates new partial settings independently from historical hydration.
 *
 * @category Port
 */
interface OrganizationSettingsPolicyCatalogPort
{
  // #region Methods
  /**
   * Method get
   *
   * @access public
   *
   * @return OrganizationSettingsPolicyDefinition registered field or exception
   */
  public function get(string $key): OrganizationSettingsPolicyDefinition;

  /**
   * Method allDefaults
   *
   * @access public
   *
   * @return array<string, array<string, mixed>> all registered section defaults
   */
  public function allDefaults(): array;

  /**
   * Method defaults
   *
   * @access public
   *
   * @return array<string, mixed> effective registered defaults for one section
   */
  public function defaults(string $section): array;

  /**
   * Method validatePartial
   *
   * @access public
   *
   * @param array<string, mixed> $partial submitted field changes
   *
   * @return void throws for unknown fields and invalid structural values
   */
  public function validatePartial(string $section, array $partial): void;

  /**
   * Method merge
   *
   * @access public
   *
   * @param array<string, mixed> $current historical stored section
   * @param array<string, mixed> $partial submitted changes
   *
   * @return array<string, mixed> stored customizations; null map entries remove customizations
   */
  public function merge(string $section, array $current, array $partial): array;
  // #endregion
}
