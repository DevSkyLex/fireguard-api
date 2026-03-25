<?php

declare(strict_types=1);

namespace Equipment\Application\Port\Outbound;

use Equipment\Domain\Model\Tag\Tag;
use Equipment\Domain\ValueObject\{EquipmentId, EquipmentOrganizationId, TagId};

/**
 * Port TagRepositoryPort.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface TagRepositoryPort
{
  // #region Methods
  /**
   * Method save.
   *
   * Persists a tag.
   *
   * @since 1.0.0
   *
   * @param Tag $tag the tag
   */
  public function save(Tag $tag): void;

  /**
   * Method findById.
   *
   * Finds a tag by identifier.
   *
   * @since 1.0.0
   *
   * @param TagId $id the tag identifier
   *
   * @return ?Tag the tag when found
   */
  public function findById(TagId $id): ?Tag;

  /**
   * Method findByNameAndOrganizationId.
   *
   * Finds a tag by name within an organization.
   *
   * @since 1.0.0
   *
   * @param string $name the tag name
   * @param EquipmentOrganizationId $organizationId the organization identifier
   *
   * @return ?Tag the tag when found
   */
  public function findByNameAndOrganizationId(string $name, EquipmentOrganizationId $organizationId): ?Tag;

  /**
   * Method findByEquipmentId.
   *
   * Lists all tags linked to an equipment.
   *
   * @since 1.0.0
   *
   * @param EquipmentId $equipmentId the equipment identifier
   *
   * @return list<Tag> the tag list
   */
  public function findByEquipmentId(EquipmentId $equipmentId): array;

  /**
   * Method isTagLinkedToEquipment.
   *
   * Checks whether a tag is linked to an equipment.
   *
   * @since 1.0.0
   *
   * @param EquipmentId $equipmentId the equipment identifier
   * @param TagId $tagId the tag identifier
   *
   * @return bool true when the tag is linked
   */
  public function isTagLinkedToEquipment(EquipmentId $equipmentId, TagId $tagId): bool;

  /**
   * Method addTagToEquipment.
   *
   * Links a tag to an equipment.
   *
   * @since 1.0.0
   *
   * @param EquipmentId $equipmentId the equipment identifier
   * @param TagId $tagId the tag identifier
   */
  public function addTagToEquipment(EquipmentId $equipmentId, TagId $tagId): void;

  /**
   * Method removeTagFromEquipment.
   *
   * Unlinks a tag from an equipment.
   *
   * @since 1.0.0
   *
   * @param EquipmentId $equipmentId the equipment identifier
   * @param TagId $tagId the tag identifier
   */
  public function removeTagFromEquipment(EquipmentId $equipmentId, TagId $tagId): void;

  /**
   * Method findTagsByEquipmentIds.
   *
   * Lists all tags for multiple equipments in a single query.
   *
   * @since 1.0.0
   *
   * @param list<EquipmentId> $equipmentIds the equipment identifiers
   *
   * @return array<string, list<Tag>> tags indexed by equipment identifier string
   */
  public function findTagsByEquipmentIds(array $equipmentIds): array;

  /**
   * Method saveAndLinkToEquipment.
   *
   * Persists a new tag and links it to an equipment atomically.
   *
   * @since 1.0.0
   *
   * @param Tag $tag the tag to persist
   * @param EquipmentId $equipmentId the equipment identifier
   */
  public function saveAndLinkToEquipment(Tag $tag, EquipmentId $equipmentId): void;

  /**
   * Method findByOrganizationId.
   *
   * Lists all tags in the organization catalog with optional search filter.
   *
   * @since 1.0.0
   *
   * @param EquipmentOrganizationId $organizationId the organization identifier
   * @param ?string $search optional substring to filter tag names (case-insensitive)
   *
   * @return list<Tag> the matched tags ordered by name ascending
   */
  public function findByOrganizationId(EquipmentOrganizationId $organizationId, ?string $search = null): array;

  /**
   * Method countByOrganizationId.
   *
   * Counts all tags in the organization catalog with optional search filter.
   *
   * @since 1.0.0
   *
   * @param EquipmentOrganizationId $organizationId the organization identifier
   * @param ?string $search optional substring to filter tag names (case-insensitive)
   *
   * @return int the total count
   */
  public function countByOrganizationId(EquipmentOrganizationId $organizationId, ?string $search = null): int;
  // #endregion
}
