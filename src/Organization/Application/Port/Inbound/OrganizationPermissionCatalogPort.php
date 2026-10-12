<?php

declare(strict_types=1);

namespace Organization\Application\Port\Inbound;

use Organization\Application\Contract\Authorization\OrganizationPermissionDefinition;

/**
 * Interface OrganizationPermissionCatalogPort
 *
 * Publishes registered permissions and denies new unresolved grants independently from persisted role history.
 *
 * @category Port
 */
interface OrganizationPermissionCatalogPort
{
  // #region Methods
  /**
   * Method definitions
   *
   * @access public
   *
   * @return list<OrganizationPermissionDefinition> ordered registered definitions
   */
  public function definitions(): array;

  /**
   * Method contains
   *
   * @access public
   *
   * @return bool whether the current composition owns this permission
   */
  public function contains(string $name): bool;

  /**
   * @return string|null registered canonical requirement, including an owner-declared historical alias
   */
  public function canonicalName(string $name): ?string;

  /**
   * Method descriptionFor
   *
   * @access public
   *
   * @return string registered description or empty for historical unknown grants
   */
  public function descriptionFor(string $name): string;

  /**
   * Method assertKnown
   *
   * @access public
   *
   * @param list<string> $permissions requested new grants
   */
  public function assertKnown(array $permissions): void;
  // #endregion
}
