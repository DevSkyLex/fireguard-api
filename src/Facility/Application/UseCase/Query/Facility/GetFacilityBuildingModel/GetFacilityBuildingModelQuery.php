<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Query\Facility\GetFacilityBuildingModel;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase GetFacilityBuildingModelQuery.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetFacilityBuildingModelQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Identifies the organization-scoped facility whose building model is requested.
   *
   * @access public
   *
   * @param string $organizationId organization scope used to authorize the lookup
   * @param string $facilityId facility whose building model is requested
   * @param bool $includeEquipment whether the caller separately has equipment-read access
   *
   * @return void
   */
  public function __construct(
    public string $organizationId,
    public string $facilityId,
    public bool $includeEquipment = false,
  ) {
  }
  // #endregion
}
