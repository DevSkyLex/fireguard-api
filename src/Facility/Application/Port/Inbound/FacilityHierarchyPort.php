<?php

declare(strict_types=1);

namespace Facility\Application\Port\Inbound;

use Facility\Application\Contract\Hierarchy\FacilityHierarchyNode;

/**
 * Port FacilityHierarchyPort.
 *
 * @category Port
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface FacilityHierarchyPort
{
  // #region Methods
  /**
   * Method lock.
   *
   * Serializes relationship writes within the caller's main transaction.
   *
   * @since 1.0.0
   */
  public function lock(string $organizationId): void;

  /**
   * Method assertGraph.
   *
   * Validates proposed nodes together against the current organization graph.
   * Existing unrelated invalid structures do not prevent an explicit repair.
   * A locked publication baseline retains unchanged published relationships
   * during descriptive edits and archival; new relationships and restores remain strict.
   *
   * @since 1.0.0
   *
   * @param list<FacilityHierarchyNode> $proposedNodes the complete final proposed rows
   * @param array<string, FacilityHierarchyNode> $originalNodes published rows read under the publication lock, retained through final validation
   */
  public function assertGraph(string $organizationId, array $proposedNodes, array $originalNodes = []): void;

  /**
   * Method allowsParent.
   *
   * @since 1.0.0
   */
  public function allowsParent(string $organizationId, string $type, ?string $parentId, ?string $facilityId = null, ?string $interventionId = null): bool;

  /**
   * Method eligibleParentIds.
   *
   * Resolves eligibility from a single organization snapshot for paginated pickers.
   *
   * @since 1.0.0
   *
   * @return list<string> eligible active parent identifiers, including compatible intervention drafts when requested
   */
  public function eligibleParentIds(string $organizationId, string $type, ?string $facilityId = null, ?string $interventionId = null): array;

  /**
   * Method issuesFor.
   *
   * @since 1.0.0
   *
   * @param list<string> $facilityIds requested accessible rows
   *
   * @return array<string, list<string>> safe diagnostic codes keyed by facility identifier
   */
  public function issuesFor(string $organizationId, array $facilityIds): array;
  // #endregion
}
