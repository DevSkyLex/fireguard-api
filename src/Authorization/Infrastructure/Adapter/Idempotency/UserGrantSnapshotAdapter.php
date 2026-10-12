<?php

declare(strict_types=1);

namespace Authorization\Infrastructure\Adapter\Idempotency;

use Authorization\Application\Contract\Grant\UserGrantSnapshot;
use Authorization\Application\Port\Inbound\UserGrantSnapshotPort;
use Authorization\Application\Port\Outbound\{PermissionCatalogPort, RoleCatalogPort};
use Doctrine\DBAL\Connection;

use function array_filter;
use function array_values;
use function sort;

/**
 * Reads Authorization-owned grants and policy publications directly on auth.
 *
 * @category Adapter
 */
final readonly class UserGrantSnapshotAdapter implements UserGrantSnapshotPort
{
  /**
   * @param Connection $connection explicit auth grant database
   * @param PermissionCatalogPort $permissions active registered vocabulary
   * @param RoleCatalogPort $roles current contributed role policy
   */
  public function __construct(private Connection $connection, private PermissionCatalogPort $permissions, private RoleCatalogPort $roles)
  {
  }

  /**
   * {@inheritDoc}
   */
  public function current(string $userId, ?string $tenantId): UserGrantSnapshot
  {
    $parameters = ['subjectType' => 'user', 'userId' => $userId, 'tenantId' => $tenantId];
    /** @var list<string> $roleNames */
    $roleNames = $this->connection->fetchFirstColumn('SELECT DISTINCT r.name FROM role_assignments a JOIN roles r ON r.id = a.role_id WHERE a.subject_type = :subjectType AND a.subject_id = :userId AND (a.expires_at IS NULL OR a.expires_at > CURRENT_TIMESTAMP) AND (a.tenant_id IS NULL OR a.tenant_id = :tenantId) AND (r.tenant_id IS NULL OR r.tenant_id = :tenantId) ORDER BY r.name', $parameters);
    /** @var list<string> $permissionNames */
    $permissionNames = $this->connection->fetchFirstColumn('SELECT DISTINCT p.name FROM role_assignments a JOIN roles r ON r.id = a.role_id JOIN role_permissions rp ON rp.role_id = r.id JOIN permissions p ON p.id = rp.permission_id WHERE a.subject_type = :subjectType AND a.subject_id = :userId AND (a.expires_at IS NULL OR a.expires_at > CURRENT_TIMESTAMP) AND (a.tenant_id IS NULL OR a.tenant_id = :tenantId) AND (r.tenant_id IS NULL OR r.tenant_id = :tenantId) ORDER BY p.name', $parameters);
    $permissionNames = array_values(array_filter($permissionNames, fn (string $name): bool => $this->permissions->contains($name)));
    $vocabulary = [];
    foreach ($this->permissions->definitions() as $definition) {
      $vocabulary[] = $definition->name;
    }
    sort($vocabulary);
    $rolePolicy = [];
    foreach ($this->roles->definitions() as $definition) {
      $grants = $definition->permissionNames;
      sort($grants);
      $rolePolicy[$definition->name] = ['permissions' => $grants, 'system' => $definition->isSystem, 'default' => $definition->defaultForNewUsers];
    }

    return new UserGrantSnapshot($roleNames, $permissionNames, ['permissions' => $vocabulary, 'roles' => $rolePolicy]);
  }
}
