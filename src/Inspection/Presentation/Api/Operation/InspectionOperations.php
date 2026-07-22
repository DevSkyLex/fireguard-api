<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Operation;

final class InspectionOperations
{
  public const string LIST_INSPECTION_RESULTS = 'inspection_list_results';

  public const string LIST_INSPECTION_STATUSES = 'inspection_list_statuses';

  public const string LIST_INSPECTOR_TYPES = 'inspection_list_inspector_types';

  public const string LIST_CHECKLIST_STATUSES = 'inspection_list_checklist_statuses';

  public const string LIST_NON_CONFORMITY_STATUSES = 'inspection_list_non_conformity_statuses';

  public const string CREATE_INSPECTION = 'inspection_create';

  public const string LIST_INSPECTIONS = 'inspection_list';

  public const string LIST_FACILITY_INSPECTIONS = 'facility_inspection_list';

  public const string GET_INSPECTION = 'inspection_get';

  public const string EDIT_INSPECTION = 'inspection_edit';

  public const string CANCEL_INSPECTION = 'inspection_cancel';

  public const string SUBMIT_INSPECTION = 'inspection_submit';

  public const string CLOSE_INSPECTION = 'inspection_close';

  public const string ADD_NON_CONFORMITY = 'inspection_add_non_conformity';

  public const string LIST_NON_CONFORMITIES = 'inspection_list_non_conformities';

  public const string LIST_ORGANIZATION_NON_CONFORMITIES = 'inspection_list_organization_non_conformities';

  public const string GET_NON_CONFORMITY = 'inspection_get_non_conformity';

  public const string UPDATE_NON_CONFORMITY_STATUS = 'inspection_update_non_conformity_status';

  public const string CREATE_CHECKLIST = 'inspection_create_checklist';

  public const string LIST_CHECKLISTS = 'inspection_list_checklists';

  public const string GET_CHECKLIST = 'inspection_get_checklist';

  public const string ARCHIVE_CHECKLIST = 'inspection_archive_checklist';

  public const string UPDATE_CHECKLIST = 'inspection_update_checklist';
}
