<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Operation;

final class InspectionOperations
{
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

  public const string GET_NON_CONFORMITY_STATISTICS = 'inspection_get_non_conformity_statistics';

  public const string GET_NON_CONFORMITY = 'inspection_get_non_conformity';

  public const string UPDATE_NON_CONFORMITY_STATUS = 'inspection_update_non_conformity_status';

  public const string CREATE_CHECKLIST = 'inspection_create_checklist';

  public const string LIST_CHECKLISTS = 'inspection_list_checklists';

  public const string GET_CHECKLIST = 'inspection_get_checklist';

  public const string ARCHIVE_CHECKLIST = 'inspection_archive_checklist';

  public const string UPDATE_CHECKLIST = 'inspection_update_checklist';

  public const string EXPORT_INSPECTIONS = 'inspection_export';

  public const string EXPORT_NON_CONFORMITIES = 'inspection_export_non_conformities';

  public const string EXPORT_INSPECTION_REPORT = 'inspection_export_report';

  public const string EXPORT_NON_CONFORMITIES_REPORT = 'inspection_export_non_conformities_report';
}
