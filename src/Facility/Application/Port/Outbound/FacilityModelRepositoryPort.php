<?php

declare(strict_types=1);

namespace Facility\Application\Port\Outbound;

use Facility\Domain\Model\FacilityModel\FacilityModel;

/**
 * Port FacilityModelRepositoryPort.
 *
 * @category Outbound Port
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface FacilityModelRepositoryPort
{
  // #region Methods
  /**
   * Method find.
   *
   * Reads a model snapshot by identifier without returning persistence objects.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $id the id
   *
   * @return ?FacilityModel the operation result
   */
  public function find(string $id): ?FacilityModel;

  /**
   * Method findByBuilding.
   *
   * Lists immutable model snapshots in one organization and building scope.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $organizationId the organization id
   * @param string $buildingId the building id
   *
   * @return list<FacilityModel>
   */
  public function findByBuilding(string $organizationId, string $buildingId): array;

  /**
   * Method insert.
   *
   * Stores a draft model while serializing the per-building model limit.
   *
   * @access public
   * @since 1.0.0
   *
   * @param FacilityModel $model the model
   *
   * @return void no return value
   */
  public function insert(FacilityModel $model): void;

  /**
   * Method update.
   *
   * Persists settings only when the stored revision matches the expected revision.
   *
   * @access public
   * @since 1.0.0
   *
   * @param FacilityModel $model the model
   * @param int $expectedRevision the expected revision
   *
   * @return void no return value
   */
  public function update(FacilityModel $model, int $expectedRevision): void;

  /**
   * Method activate.
   *
   * Activates the selected model while preserving revision preconditions.
   *
   * @access public
   * @since 1.0.0
   *
   * @param FacilityModel $model the model
   * @param int $expectedRevision the expected revision
   *
   * @return void no return value
   */
  public function activate(FacilityModel $model, int $expectedRevision): void;

  /**
   * Method delete.
   *
   * Removes the model only when the stored revision matches the expected revision.
   *
   * @access public
   * @since 1.0.0
   *
   * @param FacilityModel $model the model
   * @param int $expectedRevision the expected revision
   *
   * @return void no return value
   */
  public function delete(FacilityModel $model, int $expectedRevision): void;
  // #endregion
}
