<?php

declare(strict_types=1);

namespace Organization\Application\Port\Outbound;

use Organization\Domain\Model\OrganizationRole\OrganizationRole;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationRoleId, OrganizationRoleName};

/**
 * Port OrganizationRoleRepositoryPort.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface OrganizationRoleRepositoryPort
{
  // #region Methods
  /**
   * Method save.
   *
   * Persists an organization role aggregate.
   *
   * @since 1.0.0
   *
   * @param OrganizationRole $role the role aggregate
   */
  public function save(OrganizationRole $role): void;

  /**
   * Method remove.
   *
   * Deletes an organization role aggregate and cascades member assignments.
   *
   * @since 1.0.0
   *
   * @param OrganizationRole $role the role aggregate to delete
   */
  public function remove(OrganizationRole $role): void;

  /**
   * Method findById.
   *
   * Finds a role by identifier.
   *
   * @since 1.0.0
   *
   * @param OrganizationRoleId $id the role identifier
   *
   * @return ?OrganizationRole the role aggregate when found
   */
  public function findById(OrganizationRoleId $id): ?OrganizationRole;

  /**
   * Method findByOrganizationAndName.
   *
   * Finds a role by organization identifier and role name.
   *
   * @since 1.0.0
   *
   * @param OrganizationId $organizationId the organization identifier
   * @param OrganizationRoleName $name the role name
   *
   * @return ?OrganizationRole the role aggregate when found
   */
  public function findByOrganizationAndName(OrganizationId $organizationId, OrganizationRoleName $name): ?OrganizationRole;

  /**
   * Method findByOrganizationId.
   *
   * Lists roles belonging to an organization, ordered by name. `$limit` of
   * `null` returns the full (unpaginated) result set — the two existing
   * unpaginated callers (role name lookups for member listings) rely on this
   * default to keep receiving every role.
   *
   * @since 1.1.0
   *
   * @param OrganizationId $organizationId the organization identifier
   * @param ?int $limit the maximum number of roles to return, or null for no pagination
   * @param ?int $offset the number of roles to skip, ignored when `$limit` is null
   *
   * @return list<OrganizationRole> the roles list
   */
  public function findByOrganizationId(OrganizationId $organizationId, ?int $limit = null, ?int $offset = null): array;

  /**
   * Method countByOrganizationId.
   *
   * Counts roles belonging to an organization.
   *
   * @since 1.0.0
   *
   * @param OrganizationId $organizationId the organization identifier
   *
   * @return int the role count
   */
  public function countByOrganizationId(OrganizationId $organizationId): int;

  /**
   * Counts system roles belonging to an organization.
   *
   * @since 1.0.0
   *
   * @param OrganizationId $organizationId the organization identifier
   *
   * @return int the system role count
   */
  public function countSystemByOrganizationId(OrganizationId $organizationId): int;

  /**
   * Method findByIdsInOrganization.
   *
   * Finds roles by a scoped list of identifiers.
   *
   * @since 1.0.0
   *
   * @param OrganizationId $organizationId the organization identifier
   * @param list<OrganizationRoleId> $roleIds the role identifiers
   *
   * @return list<OrganizationRole> the matching roles
   */
  public function findByIdsInOrganization(OrganizationId $organizationId, array $roleIds): array;
  // #endregion
}
