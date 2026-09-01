<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Query\Facility\GetFacilityBuildingModel;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase GetFacilityBuildingModelQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetFacilityBuildingModelQuery implements QueryMessage
{
  // #region Constructor
  public function __construct(
    public string $organizationId,
    public string $facilityId,
  ) {
  }
  // #endregion
}
