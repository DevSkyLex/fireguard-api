<?php

declare(strict_types=1);

namespace Compliance\Application\Service;

use Compliance\Application\Contract\{FacilityComplianceView, FacilityTreeNode};

use function array_map;

/**
 * Service FacilityTreeBuilder.
 *
 * Turns the flat, already-batched {@see FacilityComplianceView} list produced
 * by {@see ComplianceRegisterAggregator::buildFacilityViews()} into a nested
 * Site -> Building -> Floor -> Zone/Area hierarchy WITHOUT issuing any
 * further port call: every statistic was already fetched in bulk (grouped by
 * facility-id list) upstream, so assembling the tree here is pure in-memory
 * work, not an additional N+1 risk. This is also why the verdict/rate are
 * reused as-is rather than recomputed — there remains exactly one definition
 * of "compliant" ({@see ComplianceStatusPolicy}).
 *
 * The synthetic `unassigned` pseudo-facility (equipment/inspections with no
 * assigned facility) is excluded: it has no place in a real facility
 * hierarchy and only exists for the compliance register's "nothing silently
 * vanishes" transparency guarantee.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FacilityTreeBuilder
{
  // #region Constants
  /**
   * Constant MAX_DEPTH.
   *
   * Guards node assembly against a corrupted parent cycle, mirroring
   * `ComplianceRegisterAggregator::MAX_PATH_DEPTH`. A facility whose parent
   * chain is corrupted (or genuinely deeper than this) still appears in the
   * tree; only its own descendants beyond the guard are dropped.
   *
   * @since 1.0.0
   *
   * @var int
   */
  private const int MAX_DEPTH = 32;
  // #endregion

  // #region Methods
  /**
   * Method build.
   *
   * @since 1.0.0
   *
   * @param list<FacilityComplianceView> $facilityViews the flat, already-graded facility compliance views (a single organization's worth)
   *
   * @return list<FacilityTreeNode> the root-level tree nodes, each carrying its nested children
   */
  public static function build(array $facilityViews): array
  {
    $viewsById = [];
    foreach ($facilityViews as $view) {
      if (ComplianceRegisterAggregator::UNASSIGNED_FACILITY_KEY === $view->facilityId) {
        continue;
      }

      $viewsById[$view->facilityId] = $view;
    }

    $childIdsByParentId = [];
    $rootIds = [];
    foreach ($viewsById as $view) {
      if (null !== $view->parentFacilityId && isset($viewsById[$view->parentFacilityId])) {
        $childIdsByParentId[$view->parentFacilityId][] = $view->facilityId;
      } else {
        // No parent, or a parent outside this organization's directory: treat as a root.
        $rootIds[] = $view->facilityId;
      }
    }

    return array_map(
      static fn (string $id): FacilityTreeNode => self::buildNode($id, $viewsById, $childIdsByParentId, 0),
      $rootIds,
    );
  }

  /**
   * Method buildNode.
   *
   * @since 1.0.0
   *
   * @param array<string, FacilityComplianceView> $viewsById
   * @param array<string, list<string>> $childIdsByParentId
   */
  private static function buildNode(string $id, array $viewsById, array $childIdsByParentId, int $depth): FacilityTreeNode
  {
    $view = $viewsById[$id];
    $childIds = $depth >= self::MAX_DEPTH ? [] : ($childIdsByParentId[$id] ?? []);

    return new FacilityTreeNode(
      id: $view->facilityId,
      name: $view->name,
      type: $view->type,
      parentFacilityId: $view->parentFacilityId,
      equipmentCount: $view->totalEquipmentCount,
      status: $view->status,
      complianceRate: $view->complianceRate(),
      children: array_map(
        static fn (string $childId): FacilityTreeNode => self::buildNode($childId, $viewsById, $childIdsByParentId, $depth + 1),
        $childIds,
      ),
    );
  }
  // #endregion
}
