<?php

declare(strict_types=1);

namespace Intervention\Application\Port\Outbound;

use Intervention\Application\Contract\Result\InterventionInspectionResult;

/**
 * Interface InterventionInspectionResultPort
 *
 * Reads owner-validated inspection facts restricted to the publication scope.
 *
 * @category Port
 */
interface InterventionInspectionResultPort
{
  /**
   * Method find
   *
   * Returns null for missing, foreign-organization or unrelated inspections.
   *
   * @access public
   *
   * @param string $organizationId publication organization
   * @param string $interventionId owning intervention
   * @param string $inspectionId result resource identifier
   *
   * @return ?InterventionInspectionResult the scoped inspection fact
   */
  public function find(string $organizationId, string $interventionId, string $inspectionId): ?InterventionInspectionResult;
}
