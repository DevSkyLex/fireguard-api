<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Persistence\Doctrine\Mapper;

use LogicException;
use Organization\Domain\Model\OrganizationRole\OrganizationRole;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationRoleId, OrganizationRoleName};
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationRecord, OrganizationRoleRecord};

/**
 * Mapper OrganizationRoleMapper.
 *
 * @category Mapper
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationRoleMapper
{
  // #region Methods
  /**
   * Method toDomain.
   *
   * Maps a Doctrine role record to a domain aggregate.
   *
   * @since 1.0.0
   *
   * @param OrganizationRoleRecord $record the persistence record
   *
   * @return OrganizationRole the domain aggregate
   */
  public static function toDomain(OrganizationRoleRecord $record): OrganizationRole
  {
    if (!$record->organization instanceof OrganizationRecord) {
      throw new LogicException('Organization role record must reference an organization.');
    }

    return OrganizationRole::reconstitute(
      id: OrganizationRoleId::fromString($record->id),
      organizationId: OrganizationId::fromString($record->organization->id),
      name: new OrganizationRoleName($record->name),
      permissions: $record->permissions,
      isSystem: $record->isSystem,
      createdAt: $record->createdAt,
    );
  }

  /**
   * Method toRecord.
   *
   * Maps an organization role domain aggregate to a Doctrine record.
   *
   * @since 1.0.0
   *
   * @param OrganizationRole $role the domain aggregate
   *
   * @return OrganizationRoleRecord the persistence record
   */
  public static function toRecord(OrganizationRole $role): OrganizationRoleRecord
  {
    $record = new OrganizationRoleRecord();
    $record->id = (string) $role->id();
    $record->name = (string) $role->name();
    $record->permissions = $role->permissions();
    $record->isSystem = $role->isSystem();
    $record->createdAt = $role->createdAt();

    return $record;
  }
  // #endregion
}
