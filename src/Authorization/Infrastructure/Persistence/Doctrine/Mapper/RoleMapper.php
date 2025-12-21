<?php

declare(strict_types=1);

namespace Authorization\Infrastructure\Persistence\Doctrine\Mapper;

use Authorization\Domain\Model\Role;
use Authorization\Domain\ValueObject\RoleId;
use Authorization\Domain\ValueObject\RoleName;
use Authorization\Infrastructure\Persistence\Doctrine\Record\RoleRecord;
use Shared\Domain\ValueObject\TenantId;

/**
 * Mapper RoleMapper.
 *
 * @category Mapper
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RoleMapper
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes the mapper with a
   * permission mapper.
   *
   * @since 1.0.0
   *
   * @param PermissionMapper $permissionMapper the permission mapper
   */
  public function __construct(
    private readonly PermissionMapper $permissionMapper,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method toDomain.
   *
   * Converts a RoleRecord to a Role domain model.
   *
   * @since 1.0.0
   *
   * @param RoleRecord $record the record to convert
   *
   * @return Role the domain model
   */
  public function toDomain(RoleRecord $record): Role
  {
    $permissions = [];
    foreach ($record->permissions as $permissionRecord) {
      $permissions[] = $this->permissionMapper->toDomain(record: $permissionRecord);
    }

    return Role::reconstitute(
      id: new RoleId(value: $record->id),
      name: new RoleName(value: $record->name),
      description: $record->description,
      isSystem: $record->isSystem,
      tenantId: null !== $record->tenantId ? TenantId::fromString(value: $record->tenantId) : null,
      createdAt: $record->createdAt,
      updatedAt: $record->updatedAt,
      permissions: $permissions,
    );
  }

  /**
   * Method toRecord.
   *
   * Converts a Role domain model
   * to a RoleRecord.
   *
   * @since 1.0.0
   *
   * @param Role            $role   the domain model to convert
   * @param RoleRecord|null $record existing record to update, or null to create new
   *
   * @return RoleRecord the Doctrine record
   */
  public function toRecord(Role $role, ?RoleRecord $record = null): RoleRecord
  {
    $record ??= new RoleRecord();

    $record->id = $role->id()->value;
    $record->name = $role->name()->value;
    $record->description = $role->description();
    $record->isSystem = $role->isSystem();
    $record->tenantId = null !== $role->tenantId() ? (string) $role->tenantId() : null;
    $record->createdAt = $role->createdAt();
    $record->updatedAt = $role->updatedAt();

    return $record;
  }
  // #endregion
}
