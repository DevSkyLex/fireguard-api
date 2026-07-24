<?php

declare(strict_types=1);

namespace Compliance\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get};
use Compliance\Presentation\Api\Dto\Output\ComplianceSummaryOutput;
use Compliance\Presentation\Api\Operation\ComplianceOperations;
use Compliance\Presentation\Api\Provider\{GetComplianceOverviewProvider, GetFacilityComplianceProvider};

/**
 * Resource ComplianceSummaryResource.
 *
 * Read-only compliance register summary: an organization rollup (current
 * snapshot, permission-aware cached) and a single-facility detail. Reads are
 * live — this is not a historical reconstruction; the register's
 * `generatedAt` is the data-as-of timestamp.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'ComplianceSummary',
  routePrefix: '/organizations',
  description: 'Regulatory compliance register summary: organization rollup and per-facility breakdown, graded compliant/at_risk/non_compliant/not_applicable from Maintenance due-status, Inspection non-conformity, and Equipment inventory counts. Requires organization.compliance.read.',
  operations: [
    new Get(
      name: ComplianceOperations::GET_ORGANIZATION_COMPLIANCE,
      uriTemplate: '/{organizationId}/compliance',
      input: false,
      output: ComplianceSummaryOutput::class,
      provider: GetComplianceOverviewProvider::class,
      security: "is_granted('ROLE_USER')",
    ),
    new Get(
      name: ComplianceOperations::GET_FACILITY_COMPLIANCE,
      uriTemplate: '/{organizationId}/facilities/{facilityId}/compliance',
      input: false,
      output: ComplianceSummaryOutput::class,
      provider: GetFacilityComplianceProvider::class,
      security: "is_granted('ROLE_USER')",
    ),
  ],
)]
final class ComplianceSummaryResource
{
}
