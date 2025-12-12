<?php

declare(strict_types=1);

namespace Authorization\Infrastructure\Persistence\Doctrine\Mapper;

use Authorization\Domain\Model\Permission;
use Authorization\Domain\ValueObject\PermissionId;
use Authorization\Domain\ValueObject\PermissionName;
use Authorization\Infrastructure\Persistence\Doctrine\Record\PermissionRecord;

/**
 * Mapper PermissionMapper
 * @final
 *
 * Maps between Permission domain model and PermissionRecord Doctrine entity.
 *
 * @category Mapper
 * @package Authorization\Infrastructure\Persistence\Doctrine\Mapper
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PermissionMapper
{
  //#region Methods
  /**
   * Method toDomain
   *
   * Converts a PermissionRecord to a 
   * Permission domain model.
   *
   * @access public
   * @since 1.0.0
   *
   * @param PermissionRecord $record The record to convert.
   *
   * @return Permission The domain model.
   */
  public function toDomain(PermissionRecord $record): Permission
  {
    return new Permission(
      id: new PermissionId(value: $record->id),
      name: new PermissionName(value: $record->name),
      description: $record->description,
      createdAt: $record->createdAt,
    );
  }

  /**
   * Method toRecord
   *
   * Converts a Permission domain model to a 
   * PermissionRecord.
   *
   * @access public
   * @since 1.0.0
   *
   * @param Permission $permission The domain model to convert.
   * @param PermissionRecord|null $record Existing record to update, or null to create new.
   *
   * @return PermissionRecord The Doctrine record.
   */
  public function toRecord(Permission $permission, ?PermissionRecord $record = null): PermissionRecord
  {
    $record ??= new PermissionRecord();

    $record->id = $permission->id()->value;
    $record->name = $permission->name()->value;
    $record->description = $permission->description();
    $record->createdAt = $permission->createdAt();

    return $record;
  }
  //#endregion
}
