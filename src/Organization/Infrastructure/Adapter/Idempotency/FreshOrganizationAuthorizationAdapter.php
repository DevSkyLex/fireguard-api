<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Adapter\Idempotency;

use Doctrine\DBAL\Connection;
use Organization\Application\Contract\Authorization\{OrganizationAccessDecision, OrganizationPermissionCatalog};
use Organization\Application\Contract\Exception\OrganizationAccessDeniedException;
use Organization\Application\Port\Inbound\{FreshOrganizationAuthorizationPort, OrganizationPermissionCatalogPort, OrganizationSystemRoleCatalogPort};

use function array_unique;
use function array_values;
use function count;
use function explode;
use function in_array;
use function is_array;
use function is_string;
use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * Class FreshOrganizationAuthorizationAdapter
 *
 * Reads only Organization-owned main tables and composes registered system grants without shared caches or Doctrine identity maps.
 *
 * @category Adapter
 */
final readonly class FreshOrganizationAuthorizationAdapter implements FreshOrganizationAuthorizationPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param Connection $connection explicitly selected main connection
   * @param OrganizationPermissionCatalogPort $catalog exact registered permission requirements
   * @param OrganizationSystemRoleCatalogPort $roles current configured canonical system grants
   *
   * @return void
   */
  public function __construct(private Connection $connection, private OrganizationPermissionCatalogPort $catalog, private OrganizationSystemRoleCatalogPort $roles)
  {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritdoc}
   */
  public function hasPermission(string $userId, string $organizationId, string $permission): bool
  {
    $permission = $this->catalog->canonicalName($permission);
    if (null === $permission) {
      return false;
    }
    if (!$this->catalog->contains($permission) || !$this->exercisable($organizationId, $permission)) {
      return false;
    }
    foreach ($this->getUserPermissions($userId, $organizationId) as $grant) {
      $grant = $this->catalog->canonicalName($grant) ?? $grant;
      if ($this->matches($grant, $permission)) {
        return true;
      }
    }

    return false;
  }

  /**
   * {@inheritdoc}
   */
  public function resolveAccess(string $userId, string $organizationId, string $permission): OrganizationAccessDecision
  {
    return $this->hasPermission($userId, $organizationId, $permission)
      ? OrganizationAccessDecision::GRANTED
      : ($this->isMemberOf($userId, $organizationId) ? OrganizationAccessDecision::MISSING_PERMISSION : OrganizationAccessDecision::OUTSIDE_SCOPE);
  }

  /**
   * {@inheritdoc}
   */
  public function isMemberOf(string $userId, string $organizationId): bool
  {
    return null !== $this->activeMemberId($userId, $organizationId);
  }

  /**
   * {@inheritdoc}
   */
  public function activeMemberId(string $userId, string $organizationId): ?string
  {
    $id = $this->connection->fetchOne('SELECT id FROM organization_members WHERE organization_id = :organizationId AND user_id = :userId AND is_active = TRUE LIMIT 1', ['organizationId' => $organizationId, 'userId' => $userId]);

    return is_string($id) ? $id : null;
  }

  /**
   * {@inheritdoc}
   */
  public function memberSatisfiesRole(string $userId, string $organizationId, string $minRole): bool
  {
    if (null === $this->activeMemberId($userId, $organizationId)) {
      return false;
    }

    return 'member' === $minRole || ('admin' === $minRole && $this->hasPermission($userId, $organizationId, 'organization.*'));
  }

  /**
   * {@inheritdoc}
   */
  public function assertGrantedPermissions(string $userId, string $organizationId, array $permissions): void
  {
    foreach ($permissions as $permission) {
      if (!$this->hasPermission($userId, $organizationId, $permission)) {
        throw OrganizationAccessDeniedException::missingPermission($permission);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getUserPermissions(string $userId, string $organizationId): array
  {
    $owner = $this->connection->fetchOne('SELECT owner_user_id FROM organizations WHERE id = :organizationId', ['organizationId' => $organizationId]);
    if ($owner === $userId) {
      return ['organization.*'];
    }
    /** @var list<array{name: string, permissions: string|array<array-key, mixed>, is_system: bool|string|int}> $rows */
    $rows = $this->connection->fetchAllAssociative(
      'SELECT r.name, r.permissions, r.is_system FROM organization_members m JOIN organization_member_roles a ON a.member_id = m.id JOIN organization_roles r ON r.id = a.role_id AND r.organization_id = m.organization_id WHERE m.organization_id = :organizationId AND m.user_id = :userId AND m.is_active = TRUE',
      ['organizationId' => $organizationId, 'userId' => $userId],
    );
    $permissions = [];
    foreach ($rows as $row) {
      $stored = is_string($row['permissions']) ? json_decode($row['permissions'], true, 16, JSON_THROW_ON_ERROR) : $row['permissions'];
      $grants = [];
      if (is_array($stored)) {
        foreach ($stored as $grant) {
          if (is_string($grant)) {
            $grants[] = $grant;
          }
        }
      }
      $permissions = [...$permissions, ...$this->roles->mergePermissions($row['name'], $grants, true === $row['is_system'] || 't' === $row['is_system'] || 1 === $row['is_system'])];
    }

    return array_values(array_unique($permissions));
  }

  /**
   * Method exercisable
   *
   * @access private
   *
   * @param string $organizationId current organization
   * @param string $permission requested registered operation permission
   *
   * @return bool whether current organization lifecycle permits the operation
   */
  private function exercisable(string $organizationId, string $permission): bool
  {
    /** @var array{status: string, is_active: bool|string|int}|false $row */
    $row = $this->connection->fetchAssociative('SELECT status, is_active FROM organizations WHERE id = :organizationId', ['organizationId' => $organizationId]);
    if (false === $row) {
      return false;
    }
    if (OrganizationPermissionCatalog::isRead($permission)) {
      return true;
    }
    $status = '' === $row['status'] ? (true === $row['is_active'] || 't' === $row['is_active'] || 1 === $row['is_active'] ? 'active' : 'suspended') : $row['status'];

    return match ($status) {
      'active' => true,
      'suspended' => OrganizationPermissionCatalog::isSuspensionEscapeHatch($permission),
      'archived' => OrganizationPermissionCatalog::isArchivalGuardedDownstream($permission),
      default => false,
    };
  }

  /**
   * Method matches
   *
   * Preserves the owner's historical segmented wildcard semantics while requirements remain registered exact keys.
   *
   * @param string $granted stored owner grant pattern
   * @param string $required registered exact requirement
   *
   * @return bool whether the current pattern matches
   */
  private function matches(string $granted, string $required): bool
  {
    if ('' === $granted || '' === $required) {
      return false;
    }
    if (in_array($granted, ['*', '*.*', '*.*.*', $required], true)) {
      return true;
    }
    $segments = explode('.', $granted);
    $requirements = explode('.', $required);
    $matches = count($segments) === count($requirements);
    foreach ($segments as $index => $segment) {
      if ('*' === $segment && $index === count($segments) - 1) {
        return true;
      }
      if (!isset($requirements[$index]) || ('*' !== $segment && $segment !== $requirements[$index])) {
        return false;
      }
    }

    return $matches;
  }
  // #endregion
}
