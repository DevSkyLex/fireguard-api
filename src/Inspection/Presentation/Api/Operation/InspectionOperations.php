<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Operation;

final class InspectionOperations
{
  public const string CREATE_INSPECTION = 'inspection_create';

  public const string LIST_INSPECTIONS = 'inspection_list';

  public const string GET_INSPECTION = 'inspection_get';

  public const string SUBMIT_INSPECTION = 'inspection_submit';

  public const string CLOSE_INSPECTION = 'inspection_close';

  public const string ADD_NON_CONFORMITY = 'inspection_add_non_conformity';

  public const string LIST_NON_CONFORMITIES = 'inspection_list_non_conformities';

  public const string UPDATE_NON_CONFORMITY_STATUS = 'inspection_update_non_conformity_status';

  public const string CREATE_CHECKLIST = 'inspection_create_checklist';

  public const string LIST_CHECKLISTS = 'inspection_list_checklists';

  public const string GET_CHECKLIST = 'inspection_get_checklist';

  public const string ARCHIVE_CHECKLIST = 'inspection_archive_checklist';
}
