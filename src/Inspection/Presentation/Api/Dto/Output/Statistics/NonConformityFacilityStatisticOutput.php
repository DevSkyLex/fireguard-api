<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Dto\Output\Statistics;

/**
 * DTO NonConformityFacilityStatisticOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class NonConformityFacilityStatisticOutput
{
  // #region Properties
  /**
   * Property id.
   *
   * The facility identifier.
   *
   * @since 1.0.0
   */
  public string $id = '';

  /**
   * Property name.
   *
   * The facility display name; null when the facility could not be
   * resolved (archived or deleted since the inspection).
   *
   * @since 1.0.0
   */
  public ?string $name = null;

  /**
   * Property open.
   *
   * The facility's open (`open`/`in_progress`) non-conformity count.
   *
   * @since 1.0.0
   */
  public int $open = 0;

  /**
   * Property critical.
   *
   * How many of those open non-conformities carry `critical` severity.
   *
   * @since 1.0.0
   */
  public int $critical = 0;
  // #endregion
}
