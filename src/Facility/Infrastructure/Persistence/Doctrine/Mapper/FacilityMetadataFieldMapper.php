<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Persistence\Doctrine\Mapper;

use Facility\Domain\Model\MetadataField\{FacilityMetadataField, FacilityMetadataFieldDefinition};
use Facility\Domain\ValueObject\{
  FacilityMetadataFieldId,
  FacilityMetadataFieldKey,
  FacilityMetadataFieldLabel,
  FacilityMetadataFieldType,
  FacilityOrganizationId,
  FacilityType
};
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityMetadataFieldRecord;
use LogicException;

/**
 * Mapper FacilityMetadataFieldMapper.
 *
 * @category Mapper
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FacilityMetadataFieldMapper
{
  // #region Methods
  /**
   * Method toDomain.
   *
   * @since 1.0.0
   *
   * @param FacilityMetadataFieldRecord $record the persistence record
   *
   * @return FacilityMetadataField the domain aggregate
   */
  public static function toDomain(FacilityMetadataFieldRecord $record): FacilityMetadataField
  {
    if (!isset($record->organizationId)) {
      throw new LogicException('Facility metadata field record must reference an organization.');
    }

    return FacilityMetadataField::reconstitute(
      id: FacilityMetadataFieldId::fromString($record->id),
      organizationId: FacilityOrganizationId::fromString($record->organizationId),
      definition: new FacilityMetadataFieldDefinition(
        new FacilityMetadataFieldKey($record->key),
        new FacilityMetadataFieldLabel($record->label),
        FacilityMetadataFieldType::from($record->fieldType),
        $record->required,
        $record->options,
        null === $record->facilityType ? null : FacilityType::from($record->facilityType),
        $record->unit,
      ),
      createdAt: $record->createdAt,
      updatedAt: $record->updatedAt,
    );
  }

  /**
   * Method toRecord.
   *
   * @since 1.0.0
   *
   * @param FacilityMetadataField $field the domain aggregate
   *
   * @return FacilityMetadataFieldRecord the persistence record
   */
  public static function toRecord(FacilityMetadataField $field): FacilityMetadataFieldRecord
  {
    $record = new FacilityMetadataFieldRecord();
    $record->id = (string) $field->id();
    $record->organizationId = (string) $field->organizationId();
    $record->key = (string) $field->key();
    $record->label = (string) $field->label();
    $record->fieldType = $field->fieldType()->value;
    $record->options = $field->options();
    $record->facilityType = $field->facilityType()?->value;
    $record->required = $field->required();
    $record->unit = $field->unit();
    $record->createdAt = $field->createdAt();
    $record->updatedAt = $field->updatedAt();

    return $record;
  }
  // #endregion
}
