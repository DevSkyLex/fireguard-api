<?php

declare(strict_types=1);

namespace Facility\Application\Contract\Model;

use DateTimeImmutable;
use Facility\Domain\Model\FacilityModel\FacilityModel;

/**
 * Contract FacilityModelView.
 *
 * @category Contract
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FacilityModelView
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Initializes the model capability with its typed dependencies and state.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $id the id
   * @param string $organizationId the organization id
   * @param string $buildingId the building id
   * @param string $fileName the file name
   * @param int $fileSize the file size
   * @param list<array{index: int, name: string}> $nodes
   * @param int $revision the revision
   * @param bool $active the active
   * @param array{scale: float, rotationDegrees: float, translation: array{x: float, y: float, z: float}} $transform
   * @param list<array{nodeIndex: int, facilityId: string}> $bindings
   * @param DateTimeImmutable $createdAt the created at
   * @param DateTimeImmutable $updatedAt the updated at
   * @param list<array{nodeIndex: int, code: 'target_unavailable'}> $bindingIssues the unavailable node associations
   *
   * @return void no return value
   */
  public function __construct(
    public string $id,
    public string $organizationId,
    public string $buildingId,
    public string $fileName,
    public int $fileSize,
    public array $nodes,
    public int $revision,
    public bool $active,
    public array $transform,
    public array $bindings,
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $updatedAt,
    public array $bindingIssues = [],
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method fromModel.
   *
   * Maps a domain model snapshot into a public application contract.
   *
   * @access public
   * @since 1.0.0
   *
   * @param FacilityModel $model the model
   * @param ?list<array{nodeIndex: int, facilityId: string}> $bindings the usable associations
   * @param list<array{nodeIndex: int, code: 'target_unavailable'}> $bindingIssues the unavailable node associations
   *
   * @return self the operation result
   */
  public static function fromModel(FacilityModel $model, ?array $bindings = null, array $bindingIssues = []): self
  {
    return new self(
      $model->id,
      $model->organizationId,
      $model->buildingId,
      $model->fileName,
      $model->fileSize,
      $model->nodes,
      $model->revision,
      $model->active,
      $model->transform->toArray(),
      $bindings ?? $model->bindings,
      $model->createdAt,
      $model->updatedAt,
      $bindingIssues,
    );
  }
  // #endregion
}
