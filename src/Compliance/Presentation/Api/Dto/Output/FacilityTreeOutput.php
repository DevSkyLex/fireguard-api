<?php

declare(strict_types=1);

namespace Compliance\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;

/**
 * DTO FacilityTreeOutput.
 *
 * The enriched facility hierarchy for an organization: nested Site ->
 * Building -> Floor -> Zone/Area nodes, each carrying an equipment count and
 * the SAME compliance verdict + rate the compliance register exposes (never
 * recomputed here).
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FacilityTreeOutput
{
  /**
   * Property organizationId.
   *
   * @since 1.0.0
   */
  #[ApiProperty(identifier: true)]
  public string $organizationId = '';

  /**
   * Property generatedAt.
   *
   * ISO 8601 datetime the tree snapshot was generated at (live read, same
   * semantics as the compliance register — never a historical
   * reconstruction).
   *
   * @since 1.0.0
   */
  public string $generatedAt = '';

  /**
   * Property nodes.
   *
   * Root-level facility nodes; each node nests its direct children under
   * `children`, recursively, in the same shape.
   *
   * @since 1.0.0
   *
   * @var list<array{
   *   id: string,
   *   name: string,
   *   type: string,
   *   parentFacilityId: ?string,
   *   equipmentCount: int,
   *   status: string,
   *   complianceRate: ?float,
   *   children: array<int, mixed>,
   * }>
   */
  public array $nodes = [];
}
