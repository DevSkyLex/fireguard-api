<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Query\Equipment\GetFacilityEquipmentSummary;

use Shared\Application\Message\QueryMessage;

/**
 * Query GetFacilityEquipmentSummaryQuery.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetFacilityEquipmentSummaryQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization whose equipment is counted
   * @param string $facilityId the published facility defining the scope
   * @param bool $includeDescendants whether published descendants share the scope
   */
  public function __construct(public string $organizationId, public string $facilityId, public bool $includeDescendants = true)
  {
  }
  // #endregion
}
