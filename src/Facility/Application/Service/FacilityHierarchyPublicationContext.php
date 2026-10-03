<?php

declare(strict_types=1);

namespace Facility\Application\Service;

use function array_keys;

/**
 * Service FacilityHierarchyPublicationContext.
 *
 * @category Service
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FacilityHierarchyPublicationContext
{
  // #region Properties
  /**
   * @var array<string, true>
   */
  private array $ids = [];

  private ?string $organizationId = null;

  /**
   * @var array<string, \Facility\Application\Contract\Hierarchy\FacilityHierarchyNode>
   */
  private array $nodes = [];

  /**
   * @var list<string>
   */
  private array $archivedTransitions = [];

  /**
   * Property originalNodes.
   *
   * Preserves the locked baseline after publication writes change the current snapshot.
   *
   * @var array<string, \Facility\Application\Contract\Hierarchy\FacilityHierarchyNode>
   */
  private array $originalNodes = [];
  // #endregion

  // #region Methods
  /**
   * @since 1.0.0
   *
   * @param string $organizationId the locked organization
   * @param list<\Facility\Application\Contract\Hierarchy\FacilityHierarchyNode> $nodes the prevalidated hierarchy changes
   * @param list<string> $archivedTransitions facilities newly becoming archived in this publication
   * @param array<string, \Facility\Application\Contract\Hierarchy\FacilityHierarchyNode> $originalNodes the locked published baseline
   */
  public function enter(string $organizationId, array $nodes, array $archivedTransitions = [], array $originalNodes = []): void
  {
    $this->organizationId = $organizationId;
    $this->ids = [];
    $this->nodes = [];
    $this->archivedTransitions = $archivedTransitions;
    $this->originalNodes = $originalNodes;
    foreach ($nodes as $node) {
      $this->ids[$node->id] = true;
      $this->nodes[$node->id] = $node;
    }
  }

  /**
   * @since 1.0.0
   *
   * @param string $organizationId the organization
   * @param string $facilityId the proposed resource
   *
   * @return bool whether its final hierarchy is validated as part of this publication
   */
  public function covers(string $organizationId, string $facilityId): bool
  {
    return $this->organizationId === $organizationId && isset($this->ids[$facilityId]);
  }

  /**
   * @since 1.0.0
   */
  public function leave(): void
  {
    $this->organizationId = null;
    $this->ids = [];
    $this->nodes = [];
    $this->archivedTransitions = [];
    $this->originalNodes = [];
  }

  /**
   * @since 1.0.0
   *
   * @param string $organizationId the organization
   * @param string $facilityId the referenced facility
   *
   * @return ?\Facility\Application\Contract\Hierarchy\FacilityHierarchyNode its merged publication state
   */
  public function node(string $organizationId, string $facilityId): ?\Facility\Application\Contract\Hierarchy\FacilityHierarchyNode
  {
    return $this->organizationId === $organizationId ? ($this->nodes[$facilityId] ?? null) : null;
  }

  /**
   * @since 1.0.0
   *
   * @return list<string> the resources whose actual final graph must be checked
   */
  public function ids(): array
  {
    return array_keys($this->ids);
  }

  /**
   * @since 1.0.0
   *
   * @return list<string> newly archived facilities whose final dependents must be checked
   */
  public function archivedTransitions(): array
  {
    return $this->archivedTransitions;
  }

  /**
   * Method originalNodes.
   *
   * Keeps final validation relative to the locked state preceding every proposal.
   *
   * @access public
   *
   * @return array<string, \Facility\Application\Contract\Hierarchy\FacilityHierarchyNode> the pre-publication published rows
   */
  public function originalNodes(): array
  {
    return $this->originalNodes;
  }
  // #endregion
}
