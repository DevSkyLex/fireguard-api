<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Operation;

/**
 * Class InspectionOperations
 *
 * Defines stable operation names for inspection API resources.
 *
 * @category Operations
 */
final class InspectionOperations
{
  // #region Constants
  public const string GET_EQUIPMENT_INSPECTION_SUMMARY = 'equipment_inspection_summary_get';

  /**
   * Constant CREATE_INSPECTION
   *
   * Creates an inspection.
   *
   * @access public
   *
   * @var string
   */
  public const string CREATE_INSPECTION = 'inspection_create';

  /**
   * Constant LIST_INSPECTIONS
   *
   * Lists inspections available to the caller.
   *
   * @access public
   *
   * @var string
   */
  public const string LIST_INSPECTIONS = 'inspection_list';

  /**
   * Constant LIST_FACILITY_INSPECTIONS
   *
   * Lists inspections for a facility.
   *
   * @access public
   *
   * @var string
   */
  public const string LIST_FACILITY_INSPECTIONS = 'facility_inspection_list';

  /**
   * Constant GET_INSPECTION
   *
   * Reads one inspection.
   *
   * @access public
   *
   * @var string
   */
  public const string GET_INSPECTION = 'inspection_get';

  /**
   * Constant EDIT_INSPECTION
   *
   * Updates an inspection.
   *
   * @access public
   *
   * @var string
   */
  public const string EDIT_INSPECTION = 'inspection_edit';

  /**
   * Constant CANCEL_INSPECTION
   *
   * Cancels an inspection.
   *
   * @access public
   *
   * @var string
   */
  public const string CANCEL_INSPECTION = 'inspection_cancel';

  /**
   * Constant SUBMIT_INSPECTION
   *
   * Submits an inspection.
   *
   * @access public
   *
   * @var string
   */
  public const string SUBMIT_INSPECTION = 'inspection_submit';

  /**
   * Constant CLOSE_INSPECTION
   *
   * Closes an inspection.
   *
   * @access public
   *
   * @var string
   */
  public const string CLOSE_INSPECTION = 'inspection_close';

  /**
   * Constant ADD_NON_CONFORMITY
   *
   * Adds a non-conformity to an inspection.
   *
   * @access public
   *
   * @var string
   */
  public const string ADD_NON_CONFORMITY = 'inspection_add_non_conformity';

  /**
   * Constant LIST_NON_CONFORMITIES
   *
   * Lists non-conformities for an inspection.
   *
   * @access public
   *
   * @var string
   */
  public const string LIST_NON_CONFORMITIES = 'inspection_list_non_conformities';

  /**
   * Constant LIST_ORGANIZATION_NON_CONFORMITIES
   *
   * Lists non-conformities scoped to an organization.
   *
   * @access public
   *
   * @var string
   */
  public const string LIST_ORGANIZATION_NON_CONFORMITIES = 'inspection_list_organization_non_conformities';

  /**
   * Constant GET_NON_CONFORMITY_STATISTICS
   *
   * Reads organization non-conformity statistics.
   *
   * @access public
   *
   * @var string
   */
  public const string GET_NON_CONFORMITY_STATISTICS = 'inspection_get_non_conformity_statistics';

  /**
   * Constant GET_NON_CONFORMITY
   *
   * Reads one non-conformity.
   *
   * @access public
   *
   * @var string
   */
  public const string GET_NON_CONFORMITY = 'inspection_get_non_conformity';

  /**
   * Constant UPDATE_NON_CONFORMITY_STATUS
   *
   * Changes a non-conformity status.
   *
   * @access public
   *
   * @var string
   */
  public const string UPDATE_NON_CONFORMITY_STATUS = 'inspection_update_non_conformity_status';

  /**
   * Constant CREATE_CHECKLIST
   *
   * Creates a checklist.
   *
   * @access public
   *
   * @var string
   */
  public const string CREATE_CHECKLIST = 'inspection_create_checklist';

  /**
   * Constant LIST_CHECKLISTS
   *
   * Lists organization checklists.
   *
   * @access public
   *
   * @var string
   */
  public const string LIST_CHECKLISTS = 'inspection_list_checklists';

  /**
   * Constant GET_CHECKLIST
   *
   * Reads one checklist.
   *
   * @access public
   *
   * @var string
   */
  public const string GET_CHECKLIST = 'inspection_get_checklist';

  /**
   * Constant ARCHIVE_CHECKLIST
   *
   * Archives a checklist.
   *
   * @access public
   *
   * @var string
   */
  public const string ARCHIVE_CHECKLIST = 'inspection_archive_checklist';

  /**
   * Constant UPDATE_CHECKLIST
   *
   * Updates checklist metadata or items.
   *
   * @access public
   *
   * @var string
   */
  public const string UPDATE_CHECKLIST = 'inspection_update_checklist';

  /**
   * Constant EXPORT_INSPECTIONS
   *
   * Exports inspection data.
   *
   * @access public
   *
   * @var string
   */
  public const string EXPORT_INSPECTIONS = 'inspection_export';

  /**
   * Constant EXPORT_NON_CONFORMITIES
   *
   * Exports non-conformity data.
   *
   * @access public
   *
   * @var string
   */
  public const string EXPORT_NON_CONFORMITIES = 'inspection_export_non_conformities';

  /**
   * Constant EXPORT_INSPECTION_REPORT
   *
   * Exports an inspection report.
   *
   * @access public
   *
   * @var string
   */
  public const string EXPORT_INSPECTION_REPORT = 'inspection_export_report';

  /**
   * Constant EXPORT_NON_CONFORMITIES_REPORT
   *
   * Exports a non-conformity report.
   *
   * @access public
   *
   * @var string
   */
  public const string EXPORT_NON_CONFORMITIES_REPORT = 'inspection_export_non_conformities_report';
  // #endregion
}
