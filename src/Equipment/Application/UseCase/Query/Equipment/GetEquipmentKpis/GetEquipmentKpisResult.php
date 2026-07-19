<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Query\Equipment\GetEquipmentKpis;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase GetEquipmentKpisResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetEquipmentKpisResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param int $totalAssets total equipment count for the organization (every status, decommissioned included)
   * @param int $compliant equipment count whose cross-module `maintenanceDueStatus` resolves to `up_to_date`
   * @param int $dueSoon equipment count whose cross-module `maintenanceDueStatus` resolves to `due_soon`
   * @param int $openNonConformities organization-wide (NOT equipment-scoped) count of non-conformities
   *                                 currently `open` or `in_progress` — see `NonConformityStatisticsPort`'s docblock
   *                                 for why this cannot be narrowed to "non-conformities for this equipment set"
   */
  public function __construct(
    public int $totalAssets,
    public int $compliant,
    public int $dueSoon,
    public int $openNonConformities,
  ) {
  }
  // #endregion
}
