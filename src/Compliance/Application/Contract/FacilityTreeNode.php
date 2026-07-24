<?php

declare(strict_types=1);

namespace Compliance\Application\Contract;

use Compliance\Domain\ValueObject\ComplianceStatus;

/**
 * Contract FacilityTreeNode.
 *
 * A single node of the enriched facility hierarchy (Site -> Building ->
 * Floor -> Zone/Area): identity, an equipment count, and the SAME graded
 * {@see ComplianceStatus} verdict + compliance rate the compliance register
 * exposes. Assembled from {@see FacilityComplianceView} by
 * {@see \Compliance\Application\Service\FacilityTreeBuilder} — the verdict is
 * never recomputed, only reshaped into a nested structure.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FacilityTreeNode
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $id the facility identifier
   * @param string $name the facility display name
   * @param string $type the facility type (`site`, `building`, `floor`, `zone`, `area`)
   * @param ?string $parentFacilityId the parent facility identifier, or null at the root of the hierarchy
   * @param int $equipmentCount total published equipment assigned to the facility
   * @param ComplianceStatus $status the graded compliance verdict, reused as-is from the compliance register
   * @param ?float $complianceRate the compliance percentage (0.0-100.0, 1 decimal), or null when undefined (no tracked equipment)
   * @param list<FacilityTreeNode> $children the facility's direct children, nested in the same shape
   */
  public function __construct(
    public string $id,
    public string $name,
    public string $type,
    public ?string $parentFacilityId,
    public int $equipmentCount,
    public ComplianceStatus $status,
    public ?float $complianceRate,
    public array $children,
  ) {
  }
  // #endregion
}
