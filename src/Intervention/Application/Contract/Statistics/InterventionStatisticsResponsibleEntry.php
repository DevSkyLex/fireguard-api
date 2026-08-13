<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Statistics;

/**
 * Contract InterventionStatisticsResponsibleEntry.
 *
 * One row of the `byResponsible` top-10 breakdown, name already resolved.
 * See {@see InterventionStatisticsSiteEntry} for why this lives under
 * `Application/Contract/` rather than inside the `GetInterventionStatistics`
 * use-case folder.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionStatisticsResponsibleEntry
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $memberId the responsible member identifier
   * @param ?string $displayName the resolved member display name, or null when it could not be resolved
   * @param int $count the number of interventions assigned to this responsible
   */
  public function __construct(
    public string $memberId,
    public ?string $displayName,
    public int $count,
  ) {
  }
  // #endregion
}
