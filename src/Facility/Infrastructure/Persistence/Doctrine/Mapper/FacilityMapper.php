<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Persistence\Doctrine\Mapper;

use Facility\Domain\Model\Facility\Facility;
use Facility\Domain\ValueObject\{
  FacilityCoordinates,
  FacilityId,
  FacilityName,
  FacilityOrganizationId,
  FacilityStatus,
  FacilityType,
  PlanGeometry
};
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use LogicException;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;

/**
 * Mapper FacilityMapper.
 *
 * @category Mapper
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FacilityMapper
{
  // #region Methods
  /**
   * Method toDomain.
   *
   * Maps a Doctrine facility record to a domain aggregate.
   *
   * @since 1.0.0
   *
   * @param FacilityRecord $record the persistence record
   *
   * @return Facility the domain aggregate
   */
  public static function toDomain(FacilityRecord $record): Facility
  {
    if (!$record->organization instanceof OrganizationRecord) {
      throw new LogicException('Facility record must reference an organization.');
    }

    return Facility::reconstitute(
      id: FacilityId::fromString($record->id),
      organizationId: FacilityOrganizationId::fromString($record->organization->id),
      parentFacilityId: null !== $record->parentFacility ? FacilityId::fromString($record->parentFacility->id) : null,
      type: FacilityType::from($record->type),
      name: new FacilityName($record->name),
      code: $record->code,
      status: FacilityStatus::from($record->status),
      address: $record->address,
      metadata: $record->metadata,
      createdAt: $record->createdAt,
      updatedAt: $record->updatedAt,
      coordinates: null !== $record->latitude && null !== $record->longitude
        ? new FacilityCoordinates($record->latitude, $record->longitude)
        : null,
      planGeometry: null !== $record->planGeometry ? PlanGeometry::fromArray($record->planGeometry) : null,
      levelIndex: $record->levelIndex,
    );
  }

  /**
   * Method toRecord.
   *
   * Maps a facility aggregate to a Doctrine record.
   *
   * @since 1.0.0
   *
   * @param Facility $facility the domain aggregate
   *
   * @return FacilityRecord the persistence record
   */
  public static function toRecord(Facility $facility): FacilityRecord
  {
    $record = new FacilityRecord();
    $record->id = (string) $facility->id();
    $record->type = $facility->type()->value;
    $record->name = (string) $facility->name();
    $record->code = $facility->code();
    $record->status = $facility->status()->value;
    $record->address = $facility->address();
    $record->latitude = $facility->coordinates()?->latitude();
    $record->longitude = $facility->coordinates()?->longitude();
    $record->metadata = $facility->metadata();
    $record->planGeometry = $facility->planGeometry()?->toArray();
    $record->levelIndex = $facility->levelIndex();
    $record->createdAt = $facility->createdAt();
    $record->updatedAt = $facility->updatedAt();

    return $record;
  }
  // #endregion
}
