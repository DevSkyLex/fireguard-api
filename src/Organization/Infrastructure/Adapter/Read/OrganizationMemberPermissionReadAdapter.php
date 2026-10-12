<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Adapter\Read;

use Doctrine\DBAL\Connection;
use Organization\Application\Port\Inbound\OrganizationSystemRoleCatalogPort;
use Organization\Application\Port\Outbound\OrganizationMemberPermissionReadPort;

use function array_unique;
use function array_values;
use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * Adapter OrganizationMemberPermissionReadAdapter.
 *
 * @category Adapter
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationMemberPermissionReadAdapter implements OrganizationMemberPermissionReadPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param Connection $connection the main database connection
   */
  public function __construct(private Connection $connection, private OrganizationSystemRoleCatalogPort $systemRoles)
  {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritDoc}
   */
  public function activeUserPermissions(string $organizationId): array
  {
    /** @var list<array{user_id: string, name: ?string, permissions: ?string, is_system: bool|string|int|null}> $rows */
    $rows = $this->connection->fetchAllAssociative(
      'SELECT m.user_id, r.name, r.permissions, r.is_system FROM organization_members m
       LEFT JOIN organization_member_roles a ON a.member_id = m.id
       LEFT JOIN organization_roles r ON r.id = a.role_id AND r.organization_id = m.organization_id
       WHERE m.organization_id = :organization AND m.is_active = TRUE ORDER BY m.user_id',
      ['organization' => $organizationId],
    );
    $permissions = [];
    foreach ($rows as $row) {
      $permissions[$row['user_id']] ??= [];
      if (null === $row['name']) {
        continue;
      }
      /** @var list<string> $stored */
      $stored = null === $row['permissions'] ? [] : json_decode($row['permissions'], true, flags: JSON_THROW_ON_ERROR);
      $grants = $this->systemRoles->mergePermissions(
        roleName: $row['name'],
        permissions: $stored,
        isSystem: true === $row['is_system'] || 't' === $row['is_system'] || 1 === $row['is_system'],
      );
      $permissions[$row['user_id']] = array_values(array_unique([...$permissions[$row['user_id']], ...$grants]));
    }

    return $permissions;
  }
  // #endregion
}
