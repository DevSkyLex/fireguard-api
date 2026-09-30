<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Query\Facility\GetFacility;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase GetFacilityQuery.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetFacilityQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Identifies a facility within its organization for retrieval.
   *
   * @access public
   *
   * @param string $organizationId organization scope used to authorize the lookup
   * @param string $facilityId facility to retrieve
   *
   * @return void
   */
  public function __construct(
    public string $organizationId,
    public string $facilityId,
  ) {
  }
  // #endregion
}
