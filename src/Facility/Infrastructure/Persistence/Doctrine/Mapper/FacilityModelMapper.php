<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Persistence\Doctrine\Mapper;

use Facility\Domain\Model\FacilityModel\FacilityModel;
use Facility\Domain\ValueObject\FacilityModelTransform;
use Facility\Infrastructure\Persistence\Doctrine\Record\{FacilityModelRecord, FacilityRecord};
use LogicException;

/**
 * Mapper FacilityModelMapper.
 *
 * @category Mapper
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FacilityModelMapper
{
  // #region Methods
  /**
   * Method toDomain.
   *
   * Reconstitutes the model snapshot from its persisted record.
   *
   * @access public
   * @since 1.0.0
   *
   * @param FacilityModelRecord $record the record
   *
   * @return FacilityModel the operation result
   */
  public static function toDomain(FacilityModelRecord $record): FacilityModel
  {
    $buildingId = $record->building?->id;
    if (null === $buildingId) {
      throw new LogicException('A persisted facility model must reference its building.');
    }

    return new FacilityModel(
      $record->id,
      $record->organizationId,
      $buildingId,
      $record->fileName,
      $record->storagePath,
      $record->fileSize,
      $record->nodes,
      $record->revision,
      $record->active,
      FacilityModelTransform::fromArray($record->transform),
      $record->bindings,
      $record->createdAt,
      $record->updatedAt,
    );
  }

  /**
   * Method toRecord.
   *
   * Maps immutable metadata and mutable settings into their persistence record.
   *
   * @access public
   * @since 1.0.0
   *
   * @param FacilityModel $model the model
   * @param FacilityRecord $building the building
   *
   * @return FacilityModelRecord the operation result
   */
  public static function toRecord(FacilityModel $model, FacilityRecord $building): FacilityModelRecord
  {
    $record = new FacilityModelRecord();
    $record->id = $model->id;
    $record->organizationId = $model->organizationId;
    $record->building = $building;
    $record->fileName = $model->fileName;
    $record->storagePath = $model->storagePath;
    $record->fileSize = $model->fileSize;
    $record->nodes = $model->nodes;
    $record->revision = $model->revision;
    $record->active = $model->active;
    $record->activeBuildingId = $model->active ? $model->buildingId : null;
    $record->transform = $model->transform->toArray();
    $record->bindings = $model->bindings;
    $record->createdAt = $model->createdAt;
    $record->updatedAt = $model->updatedAt;

    return $record;
  }
  // #endregion
}
