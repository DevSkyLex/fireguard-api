<?php

declare(strict_types=1);

namespace Compliance\Presentation\Api\Operation;

/**
 * Operation ComplianceOperations.
 *
 * @category Operation
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ComplianceOperations
{
  public const string GET_ORGANIZATION_COMPLIANCE = 'getOrganizationCompliance';

  public const string GET_FACILITY_COMPLIANCE = 'getFacilityCompliance';

  public const string GET_FACILITY_TREE = 'getFacilityTree';

  public const string EXPORT_ORGANIZATION_SAFETY_REGISTER = 'exportOrganizationSafetyRegister';

  public const string EXPORT_FACILITY_SAFETY_REGISTER = 'exportFacilitySafetyRegister';
}
