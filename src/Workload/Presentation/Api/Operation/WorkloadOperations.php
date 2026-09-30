<?php

declare(strict_types=1);

namespace Workload\Presentation\Api\Operation;

/**
 * WorkloadOperations.
 *
 * @category Workload
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class WorkloadOperations
{
  /**
   * Constant GET_WORKLOAD
   */
  public const string GET_WORKLOAD = 'workload_get';

  /**
   * Constant ASSESS_WORKLOAD
   */
  public const string ASSESS_WORKLOAD = 'workload_assess';

  /**
   * Constant GET_SETTINGS
   */
  public const string GET_SETTINGS = 'workload_settings_get';

  /**
   * Constant SAVE_SETTINGS
   */
  public const string SAVE_SETTINGS = 'workload_settings_save';

  /**
   * Constant GET_CAPACITY
   */
  public const string GET_CAPACITY = 'workload_capacity_get';

  /**
   * Constant SAVE_CAPACITY
   */
  public const string SAVE_CAPACITY = 'workload_capacity_save';

  /**
   * Constant GET_EXCEPTIONS
   */
  public const string GET_EXCEPTIONS = 'workload_exceptions_get';

  /**
   * Constant CREATE_EXCEPTION
   */
  public const string CREATE_EXCEPTION = 'workload_exception_create';

  /**
   * Constant CANCEL_EXCEPTION
   */
  public const string CANCEL_EXCEPTION = 'workload_exception_cancel';
}
