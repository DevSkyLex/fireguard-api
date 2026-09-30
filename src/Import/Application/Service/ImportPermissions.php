<?php

declare(strict_types=1);

namespace Import\Application\Service;

use Import\Domain\ValueObject\ImportKind;

/** Permission names are part of the Organization public authorization contract. */
final class ImportPermissions
{
  /**
   * Method read.
   *
   * Returns the read permission associated with an import kind.
   *
   * @access public
   *
   * @static
   *
   * @param ImportKind $kind the import kind
   *
   * @return string the permission identifier
   */
  public static function read(ImportKind $kind): string
  {
    return match ($kind) {
      ImportKind::EQUIPMENT => 'organization.equipment.read',
      ImportKind::FACILITY => 'organization.facilities.read',
      ImportKind::MEMBER => 'organization.members.read',
    };
  }

  /**
   * Method write.
   *
   * Returns the write or management permission associated with an import kind.
   *
   * @access public
   *
   * @static
   *
   * @param ImportKind $kind the import kind
   *
   * @return string the permission identifier
   */
  public static function write(ImportKind $kind): string
  {
    return match ($kind) {
      ImportKind::EQUIPMENT => 'organization.equipment.write',
      ImportKind::FACILITY => 'organization.facilities.write',
      ImportKind::MEMBER => 'organization.members.manage',
    };
  }
}
