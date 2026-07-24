<?php

declare(strict_types=1);

namespace Compliance\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;

/**
 * DTO ComplianceSummaryOutput.
 *
 * Shared output shape for both the organization rollup (`facilities` holds
 * every organization facility) and the single-facility detail endpoint
 * (`facilities` holds exactly one entry, `organizationStatus`/`totals`
 * reflect that single facility).
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ComplianceSummaryOutput
{
  /**
   * Property organizationId.
   *
   * @since 1.0.0
   */
  #[ApiProperty(identifier: true)]
  public string $organizationId = '';

  /**
   * Property facilityId.
   *
   * Set only when this output represents a single-facility detail.
   *
   * @since 1.0.0
   */
  public ?string $facilityId = null;

  /**
   * Property generatedAt.
   *
   * ISO 8601 datetime the register snapshot was generated at.
   *
   * @since 1.0.0
   */
  public string $generatedAt = '';

  /**
   * Property organizationStatus.
   *
   * One of `compliant`, `at_risk`, `non_compliant`, `not_applicable`.
   *
   * @since 1.0.0
   */
  public string $organizationStatus = '';

  /**
   * Property totals.
   *
   * Map of `totalEquipmentCount`, `activeEquipmentCount`,
   * `upToDateEquipmentCount`, `dueSoonEquipmentCount`, `overdueEquipmentCount`,
   * `unscheduledEquipmentCount`, `trackedEquipmentCount`, `complianceRate`,
   * `openLowNonConformityCount`, `openMediumNonConformityCount`,
   * `openHighNonConformityCount`, `openCriticalNonConformityCount` => count.
   *
   * `trackedEquipmentCount` (up-to-date + due-soon + overdue) is the
   * denominator `complianceRate` is derived from — see
   * {@see \Compliance\Application\Contract\FacilityComplianceView::computeComplianceRate()}
   * for the formula. `complianceRate` is a percentage (0.0-100.0, 1 decimal)
   * or `null` when `trackedEquipmentCount` is 0 (undefined, NOT 0%).
   *
   * @since 1.0.0
   *
   * @var array<string, int|float|null>
   */
  public array $totals = [];

  /**
   * Property facilities.
   *
   * @since 1.0.0
   *
   * @var list<array{
   *   facilityId: string,
   *   name: string,
   *   type: string,
   *   parentFacilityId: ?string,
   *   path: string,
   *   status: string,
   *   totalEquipmentCount: int,
   *   activeEquipmentCount: int,
   *   upToDateEquipmentCount: int,
   *   dueSoonEquipmentCount: int,
   *   overdueEquipmentCount: int,
   *   unscheduledEquipmentCount: int,
   *   trackedEquipmentCount: int,
   *   complianceRate: ?float,
   *   openLowNonConformityCount: int,
   *   openMediumNonConformityCount: int,
   *   openHighNonConformityCount: int,
   *   openCriticalNonConformityCount: int,
   *   lastInspectionAt: ?string,
   * }>
   */
  public array $facilities = [];
}
