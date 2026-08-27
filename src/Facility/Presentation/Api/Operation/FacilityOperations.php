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
   * Constant LIST_FACILITY_CHILDREN.
   *
   * @var string
   */
  public const string LIST_FACILITY_CHILDREN = 'facility_list_children';

  /**
   * Constant LIST_FACILITY_DESCENDANTS.
   *
   * @var string
   */
  public const string LIST_FACILITY_DESCENDANTS = 'facility_list_descendants';

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
   * Constant RESTORE_FACILITY.
   *
   * @var string
   */
  public const string RESTORE_FACILITY = 'facility_restore';

  /**
   * Constant MOVE_FACILITY.
   *
   * @var string
   */
  public const string MOVE_FACILITY = 'facility_move';

  /**
   * Constant SET_FACILITY_PLAN_GEOMETRY.
   *
   * @var string
   */
  public const string SET_FACILITY_PLAN_GEOMETRY = 'facility_plan_geometry_set';

  /**
   * Constant GET_FACILITY_PLAN_OVERLAY.
   *
   * @var string
   */
  public const string GET_FACILITY_PLAN_OVERLAY = 'facility_plan_overlay_get';

  /**
   * Constant SET_PRIMARY_FACILITY_ATTACHMENT.
   *
   * @var string
   */
  public const string SET_PRIMARY_FACILITY_ATTACHMENT = 'facility_attachment_set_primary';

  /**
   * Constant DOWNLOAD_FACILITY_ATTACHMENT.
   *
   * @var string
   */
  public const string DOWNLOAD_FACILITY_ATTACHMENT = 'download_facility_attachment';

  /**
   * Constant DUPLICATE_FACILITY_SUBTREE.
   *
   * @var string
   */
  public const string DUPLICATE_FACILITY_SUBTREE = 'facility_duplicate_subtree';

  /**
   * Constant CREATE_FACILITY_METADATA_FIELD.
   *
   * @var string
   */
  public const string CREATE_FACILITY_METADATA_FIELD = 'facility_metadata_field_create';

  /**
   * Constant LIST_FACILITY_METADATA_FIELDS.
   *
   * @var string
   */
  public const string LIST_FACILITY_METADATA_FIELDS = 'facility_metadata_field_list';

  /**
   * Constant UPDATE_FACILITY_METADATA_FIELD.
   *
   * @var string
   */
  public const string UPDATE_FACILITY_METADATA_FIELD = 'facility_metadata_field_update';

  /**
   * Constant DELETE_FACILITY_METADATA_FIELD.
   *
   * @var string
   */
  public const string DELETE_FACILITY_METADATA_FIELD = 'facility_metadata_field_delete';
  // #endregion
}
