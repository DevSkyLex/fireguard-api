<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Dto\Output\Statistics;

/**
 * DTO InterventionSiteStatisticOutput.
 *
 * One row of the `bySite` top-10 breakdown.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InterventionSiteStatisticOutput
{
  // #region Properties
  /**
   * Property siteId.
   *
   * @since 1.0.0
   */
  public string $siteId = '';

  /**
   * Property siteName.
   *
   * @since 1.0.0
   */
  public ?string $siteName = null;

  /**
   * Property count.
   *
   * @since 1.0.0
   */
  public int $count = 0;
  // #endregion
}
