<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Statistics;

/**
 * Contract InterventionStatisticsSiteEntry.
 *
 * One row of the `bySite` top-10 breakdown, name already resolved. Lives
 * beside the Result rather than inside the `GetInterventionStatistics`
 * use-case folder because `Architecture\Unit\ApplicationNamingTest` requires
 * every class directly under `Application/UseCase/` to end with `Command`,
 * `Query`, `Handler`, or `Result` — an auxiliary read model does not fit that
 * suffix contract.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionStatisticsSiteEntry
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $siteId the site identifier
   * @param ?string $siteName the resolved site display name, or null when it could not be resolved
   * @param int $count the number of interventions at this site
   */
  public function __construct(
    public string $siteId,
    public ?string $siteName,
    public int $count,
  ) {
  }
  // #endregion
}
