<?php

declare(strict_types=1);

namespace Compliance\Presentation\Api\Operation;

/**
 * Operation ComplianceOperations.
 *
 * @category Operation
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ComplianceOperations
{
  /**
   * Constant GET_ORGANIZATION_COMPLIANCE
   */
  public const string GET_ORGANIZATION_COMPLIANCE = 'getOrganizationCompliance';

  /**
   * Constant GET_FACILITY_COMPLIANCE
   */
  public const string GET_FACILITY_COMPLIANCE = 'getFacilityCompliance';

  /**
   * Constant GET_FACILITY_TREE
   */
  public const string GET_FACILITY_TREE = 'getFacilityTree';

  /**
   * Constant EXPORT_ORGANIZATION_SAFETY_REGISTER
   */
  public const string EXPORT_ORGANIZATION_SAFETY_REGISTER = 'exportOrganizationSafetyRegister';

  /**
   * Constant EXPORT_FACILITY_SAFETY_REGISTER
   */
  public const string EXPORT_FACILITY_SAFETY_REGISTER = 'exportFacilitySafetyRegister';

  /**
   * Constant CREATE_SAFETY_REGISTER_SNAPSHOT
   */
  public const string CREATE_SAFETY_REGISTER_SNAPSHOT = 'createSafetyRegisterSnapshot';

  /**
   * Constant LIST_SAFETY_REGISTER_SNAPSHOTS
   */
  public const string LIST_SAFETY_REGISTER_SNAPSHOTS = 'listSafetyRegisterSnapshots';

  /**
   * Constant DOWNLOAD_SAFETY_REGISTER_SNAPSHOT
   */
  public const string DOWNLOAD_SAFETY_REGISTER_SNAPSHOT = 'downloadSafetyRegisterSnapshot';
}
