<?php

declare(strict_types=1);

namespace Workload\Presentation\Api\Operation;

/**
 * WorkloadOperations.
 *
 * @category Workload
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class WorkloadOperations
{
  public const string GET_WORKLOAD = 'workload_get';

  public const string ASSESS_WORKLOAD = 'workload_assess';

  public const string GET_SETTINGS = 'workload_settings_get';

  public const string SAVE_SETTINGS = 'workload_settings_save';

  public const string GET_CAPACITY = 'workload_capacity_get';

  public const string SAVE_CAPACITY = 'workload_capacity_save';

  public const string GET_EXCEPTIONS = 'workload_exceptions_get';

  public const string CREATE_EXCEPTION = 'workload_exception_create';

  public const string CANCEL_EXCEPTION = 'workload_exception_cancel';
}
