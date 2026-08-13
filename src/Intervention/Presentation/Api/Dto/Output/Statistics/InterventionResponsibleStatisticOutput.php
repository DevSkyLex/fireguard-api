<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Dto\Output\Statistics;

/**
 * DTO InterventionResponsibleStatisticOutput.
 *
 * One row of the `byResponsible` top-10 breakdown.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InterventionResponsibleStatisticOutput
{
  // #region Properties
  /**
   * Property memberId.
   *
   * @since 1.0.0
   */
  public string $memberId = '';

  /**
   * Property displayName.
   *
   * @since 1.0.0
   */
  public ?string $displayName = null;

  /**
   * Property count.
   *
   * @since 1.0.0
   */
  public int $count = 0;
  // #endregion
}
