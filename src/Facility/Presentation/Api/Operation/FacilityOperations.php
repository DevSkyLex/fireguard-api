<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Operation;

/**
 * Facility operation names.
 *
 * @category Operation
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FacilityOperations
{
  // #region Constants
  /**
   * Constant CREATE_FACILITY.
   *
   * @var string
   */
  public const string CREATE_FACILITY = 'facility_create';

  /**
   * Constant LIST_FACILITIES.
   *
   * @var string
   */
  public const string LIST_FACILITIES = 'facility_list';

  /**
   * Constant GET_FACILITY.
   *
   * @var string
   */
  public const string GET_FACILITY = 'facility_get';

  /**
   * Constant UPDATE_FACILITY.
   *
   * @var string
   */
  public const string UPDATE_FACILITY = 'facility_update';

  /**
   * Constant ARCHIVE_FACILITY.
   *
   * @var string
   */
  public const string ARCHIVE_FACILITY = 'facility_archive';

  /**
   * Constant MOVE_FACILITY.
   *
   * @var string
   */
  public const string MOVE_FACILITY = 'facility_move';
  // #endregion
}
