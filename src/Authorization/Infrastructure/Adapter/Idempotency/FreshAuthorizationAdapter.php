<?php

declare(strict_types=1);

namespace Authorization\Infrastructure\Adapter\Idempotency;

use Authorization\Application\Port\Inbound\FreshAuthorizationPort;
use Authorization\Application\Port\Outbound\PermissionCatalogPort;
use Authorization\Domain\ValueObject\PermissionName;
use Doctrine\DBAL\Connection;

/**
 * Class FreshAuthorizationAdapter
 *
 * Reads only Authorization-owned tables on the explicitly supplied auth connection, independently from request/shared grant caches.
 *
 * @category Adapter
 */
final readonly class FreshAuthorizationAdapter implements FreshAuthorizationPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param Connection $connection explicitly wired auth connection
   * @param PermissionCatalogPort $catalog registered technical permissions
   *
   * @return void
   */
  public function __construct(private Connection $connection, private PermissionCatalogPort $catalog)
  {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritdoc}
   */
  public function hasPermission(string $userId, string $permission, ?string $tenantId): bool
  {
    if (!$this->catalog->contains($permission)) {
      return false;
    }
    /** @var list<string> $grants */
    $grants = $this->connection->fetchFirstColumn(
      'SELECT DISTINCT p.name FROM role_assignments a JOIN roles r ON r.id = a.role_id JOIN role_permissions rp ON rp.role_id = r.id JOIN permissions p ON p.id = rp.permission_id WHERE a.subject_type = :subjectType AND a.subject_id = :userId AND (a.tenant_id IS NULL OR a.tenant_id = :tenantId) AND (r.tenant_id IS NULL OR r.tenant_id = :tenantId) AND (a.expires_at IS NULL OR a.expires_at > CURRENT_TIMESTAMP)',
      ['subjectType' => 'user', 'userId' => $userId, 'tenantId' => $tenantId],
    );
    $required = new PermissionName($permission);
    foreach ($grants as $grant) {
      if (new PermissionName($grant)->matches($required)) {
        return true;
      }
    }

    return false;
  }

  /**
   * {@inheritdoc}
   */
  public function hasRole(string $userId, string $roleName, ?string $tenantId): bool
  {
    return false !== $this->connection->fetchOne(
      'SELECT 1 FROM role_assignments a JOIN roles r ON r.id = a.role_id WHERE a.subject_type = :subjectType AND a.subject_id = :userId AND r.name = :roleName AND (a.tenant_id IS NULL OR a.tenant_id = :tenantId) AND (r.tenant_id IS NULL OR r.tenant_id = :tenantId) AND (a.expires_at IS NULL OR a.expires_at > CURRENT_TIMESTAMP) LIMIT 1',
      ['subjectType' => 'user', 'userId' => $userId, 'roleName' => $roleName, 'tenantId' => $tenantId],
    );
  }

  /**
   * {@inheritdoc}
   */
  public function roleNames(string $userId, ?string $tenantId): array
  {
    /** @var list<string> $roles */
    $roles = $this->connection->fetchFirstColumn(
      'SELECT DISTINCT r.name FROM role_assignments a JOIN roles r ON r.id = a.role_id WHERE a.subject_type = :subjectType AND a.subject_id = :userId AND (a.tenant_id IS NULL OR a.tenant_id = :tenantId) AND (r.tenant_id IS NULL OR r.tenant_id = :tenantId) AND (a.expires_at IS NULL OR a.expires_at > CURRENT_TIMESTAMP) ORDER BY r.name',
      ['subjectType' => 'user', 'userId' => $userId, 'tenantId' => $tenantId],
    );

    return $roles;
  }
  // #endregion
}
