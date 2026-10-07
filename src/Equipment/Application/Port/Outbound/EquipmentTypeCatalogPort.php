<?php

declare(strict_types=1);

namespace Equipment\Application\Port\Outbound;

use Equipment\Domain\Exception\EquipmentTypeCatalogException;
use Equipment\Domain\Model\EquipmentTypeCatalog\EquipmentTypeDefinition;

/**
 * Port EquipmentTypeCatalogPort.
 *
 * Reads default and organization-owned types and stores descriptors with optimistic
 * revision control. Historical assignments remain valid without permitting new archived assignments.
 *
 * @category Port
 */
interface EquipmentTypeCatalogPort
{
  // #region Methods
  /**
   * Method list.
   *
   * @access public
   *
   * @param string $organizationId owning organization
   *
   * @return list<EquipmentTypeDefinition> complete catalog, including archives
   */
  public function list(string $organizationId): array;

  /**
   * Method find.
   *
   * @access public
   *
   * @param string $organizationId owning organization
   * @param string $typeCode stable type code
   *
   * @return EquipmentTypeDefinition|null descriptor or null when unknown
   */
  public function find(string $organizationId, string $typeCode): ?EquipmentTypeDefinition;

  /**
   * Method save.
   *
   * @access public
   *
   * @param string $organizationId owning organization
   * @param EquipmentTypeDefinition $definition descriptor to persist
   * @param int|null $expectedRevision null creates; a revision updates an existing descriptor
   *
   * @return void
   *
   * @throws EquipmentTypeCatalogException on duplicate or stale revision
   */
  public function save(string $organizationId, EquipmentTypeDefinition $definition, ?int $expectedRevision): void;

  /**
   * Method validateAvailableType.
   *
   * Unchanged historical codes are preserved. Creation and type changes require
   * an active descriptor in this organization's catalog.
   *
   * @access public
   *
   * @param string $organizationId owning organization
   * @param string $type candidate type code
   * @param string|null $existingType current persisted code, null for creation
   *
   * @return void
   *
   * @throws EquipmentTypeCatalogException when unknown or archived
   */
  public function validateAvailableType(string $organizationId, string $type, ?string $existingType = null): void;
  // #endregion
}
