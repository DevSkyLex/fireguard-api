<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\Persistence\Doctrine\Mapper;

use Equipment\Domain\Model\Tag\Tag;
use Equipment\Domain\ValueObject\{EquipmentOrganizationId, TagId};
use Equipment\Infrastructure\Persistence\Doctrine\Record\TagRecord;
use LogicException;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;

/**
 * Mapper TagMapper.
 *
 * @category Mapper
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class TagMapper
{
  // #region Methods
  /**
   * Method toDomain.
   *
   * @since 1.0.0
   */
  public static function toDomain(TagRecord $record): Tag
  {
    if (!$record->organization instanceof OrganizationRecord) {
      throw new LogicException('Tag record must reference an organization.');
    }

    return Tag::reconstitute(
      id: TagId::fromString($record->id),
      organizationId: EquipmentOrganizationId::fromString($record->organization->id),
      name: $record->name,
      createdAt: $record->createdAt,
    );
  }

  /**
   * Method toRecord.
   *
   * @since 1.0.0
   */
  public static function toRecord(Tag $tag): TagRecord
  {
    $record = new TagRecord();
    $record->id = (string) $tag->id();
    $record->name = $tag->name();
    $record->createdAt = $tag->createdAt();

    return $record;
  }
  // #endregion
}
