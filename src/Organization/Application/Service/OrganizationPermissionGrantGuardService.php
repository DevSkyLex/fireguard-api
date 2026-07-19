<?php

declare(strict_types=1);

namespace Organization\Application\Service;

use Organization\Application\Port\Inbound\{OrganizationAuthorizationPort, OrganizationPermissionGrantGuardPort};
use Organization\Application\Port\Outbound\OrganizationRoleRepositoryPort;
use Organization\Domain\Event\Security\OrganizationPermissionGrantDeniedEvent;
use Organization\Domain\Exception\OrganizationAccessDeniedException;
use Organization\Domain\ValueObject\OrganizationRoleId;
use Shared\Application\Port\Outbound\EventDispatcherPort;

/**
 * Service OrganizationPermissionGrantGuardService.
 *
 * Enforces the no-privilege-escalation invariant across every surface that
 * grants permissions or assigns roles (role create/update, direct role
 * assignment, member add, member invitation): an actor may only grant
 * permissions it already holds (wildcard-aware via the authorization port).
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationPermissionGrantGuardService implements OrganizationPermissionGrantGuardPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the OrganizationPermissionGrantGuardService class.
   *
   * @since 1.0.0
   *
   * @param OrganizationAuthorizationPort $authorization the authorization port (effective permission resolution)
   * @param OrganizationRoleRepositoryPort $roleRepository the role repository port
   * @param EventDispatcherPort $eventDispatcher the domain event dispatcher (audit trail)
   */
  public function __construct(
    private OrganizationAuthorizationPort $authorization,
    private OrganizationRoleRepositoryPort $roleRepository,
    private EventDispatcherPort $eventDispatcher,
  ) {
  }
  // #endregion

  // #region Methods
  public function assertCanGrantPermissions(string $actorUserId, string $organizationId, array $permissions): void
  {
    $this->assertPermissionsGrantable($actorUserId, $organizationId, $permissions, 'grant_permissions');
  }

  public function assertCanAssignRoles(string $actorUserId, string $organizationId, array $roleIds): void
  {
    foreach ($roleIds as $roleId) {
      $role = $this->roleRepository->findById(OrganizationRoleId::fromString($roleId));

      if (null === $role || (string) $role->organizationId() !== $organizationId) {
        // Unknown / cross-organization role: nothing is granted here, and the
        // owning use case rejects it separately.
        continue;
      }

      $this->assertPermissionsGrantable($actorUserId, $organizationId, $role->permissions(), 'assign_roles');
    }
  }

  /**
   * Method assertPermissionsGrantable.
   *
   * Checks every permission against the actor's effective set; on the first
   * one the actor does not hold, emits the audit denial event and throws.
   *
   * @since 1.0.0
   *
   * @param string $actorUserId the acting user identifier
   * @param string $organizationId the organization identifier
   * @param list<string> $permissions the permissions being granted
   * @param string $context the guarded surface (grant_permissions or assign_roles)
   *
   * @throws OrganizationAccessDeniedException when the actor lacks a granted permission
   */
  private function assertPermissionsGrantable(string $actorUserId, string $organizationId, array $permissions, string $context): void
  {
    foreach ($permissions as $permission) {
      if (!$this->authorization->hasPermission($actorUserId, $organizationId, $permission)) {
        $this->eventDispatcher->dispatch(new OrganizationPermissionGrantDeniedEvent(
          organizationId: $organizationId,
          actorUserId: $actorUserId,
          deniedPermission: $permission,
          context: $context,
        ));

        throw OrganizationAccessDeniedException::cannotGrantPermission($permission);
      }
    }
  }
  // #endregion
}
