<?php

declare(strict_types=1);

namespace Facility\Application\Service;

use Facility\Application\Contract\Model\FacilityModelView;
use Facility\Domain\Model\FacilityModel\FacilityModel;

/**
 * Factory FacilityModelViewFactory.
 *
 * Keeps domain mapping out of the public scalar contract.
 *
 * @category Factory
 */
final readonly class FacilityModelViewFactory
{
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
   * @return FacilityModelView the operation result
   */
  public static function fromModel(FacilityModel $model, ?array $bindings = null, array $bindingIssues = []): FacilityModelView
  {
    return new FacilityModelView(
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
