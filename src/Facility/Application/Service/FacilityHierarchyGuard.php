<?php

declare(strict_types=1);

namespace Facility\Application\Service;

use Facility\Application\Contract\Hierarchy\FacilityHierarchyNode;
use Facility\Application\Port\Inbound\FacilityHierarchyPort;
use Facility\Application\Port\Outbound\FacilityHierarchySnapshotPort;
use Facility\Domain\Exception\FacilityHierarchyException;
use Facility\Domain\ValueObject\{FacilityHierarchyPolicy, FacilityType};
use Symfony\Component\DependencyInjection\Attribute\Autowire;

use function array_key_exists;
use function count;

/**
 * Service FacilityHierarchyGuard.
 *
 * @category Service
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FacilityHierarchyGuard implements FacilityHierarchyPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   */
  public function __construct(
    private FacilityHierarchySnapshotPort $snapshot,
    #[Autowire('%facility.hierarchy.max_depth%')]
    private int $maxDepth = 8,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method lock.
   *
   * @since 1.0.0
   */
  public function lock(string $organizationId): void
  {
    $this->snapshot->lock($organizationId);
  }

  /**
   * Method assertGraph.
   *
   * @since 1.0.0
   *
   * @param list<FacilityHierarchyNode> $proposedNodes proposed final rows
   * @param array<string, FacilityHierarchyNode> $originalNodes locked publication baseline for unchanged published relationships
   */
  public function assertGraph(string $organizationId, array $proposedNodes, array $originalNodes = []): void
  {
    $previous = $this->snapshot->load($organizationId);
    $graph = $previous;
    foreach ($proposedNodes as $node) {
      $graph[$node->id] = $node;
    }
    foreach ($proposedNodes as $node) {
      if (!$this->retainsPublishedRelationship($node, $originalNodes[$node->id] ?? null)) {
        $this->assertNode($node, $graph);
        $this->assertSubtreeDepth($node, $graph);
      }
      $original = $originalNodes[$node->id] ?? $previous[$node->id] ?? null;
      if (null !== $original && $original->type !== $node->type) {
        foreach ($graph as $child) {
          if ($child->parentFacilityId === $node->id) {
            FacilityHierarchyPolicy::assertParent($this->type($child->type), $this->type($node->type));
          }
        }
      }
    }
  }

  /**
   * Method allowsParent.
   *
   * @since 1.0.0
   */
  public function allowsParent(string $organizationId, string $type, ?string $parentId, ?string $facilityId = null, ?string $interventionId = null): bool
  {
    return $this->allowsInGraph($this->snapshot->load($organizationId), $type, $parentId, $facilityId, interventionId: $interventionId);
  }

  /**
   * Method eligibleParentIds.
   *
   * @since 1.0.0
   *
   * @return list<string> eligible parent identifiers
   */
  public function eligibleParentIds(string $organizationId, string $type, ?string $facilityId = null, ?string $interventionId = null): array
  {
    $graph = $this->snapshot->load($organizationId);
    $ids = [];
    $height = null === $facilityId ? 0 : $this->subtreeHeight($facilityId, $graph);
    foreach ($graph as $parent) {
      if (('published' === $parent->publicationState
          || (null !== $interventionId && 'draft' === $parent->publicationState && $interventionId === $parent->interventionId))
        && 'active' === $parent->status
        && $this->allowsInGraph($graph, $type, $parent->id, $facilityId, $height, $interventionId)) {
        $ids[] = $parent->id;
      }
    }

    return $ids;
  }

  /**
   * Method issuesFor.
   *
   * @since 1.0.0
   *
   * @param list<string> $facilityIds accessible facilities being projected
   *
   * @return array<string, list<string>> diagnostic codes without parent identities
   */
  public function issuesFor(string $organizationId, array $facilityIds): array
  {
    $graph = $this->snapshot->load($organizationId);
    $issues = [];
    foreach ($facilityIds as $id) {
      if (isset($graph[$id])) {
        $issues[$id] = $this->nodeIssues($graph[$id], $graph);
      }
    }

    return $issues;
  }

  /**
   * Method retainsPublishedRelationship.
   *
   * Uses the pre-publication baseline at both validation stages, so persisted
   * intermediate writes cannot turn a newly changed relationship into a retained one.
   *
   * @access private
   *
   * @param FacilityHierarchyNode $node the final proposed or persisted row
   * @param ?FacilityHierarchyNode $original the row read under the organization lock
   *
   * @return bool whether descriptive edits or archival leave its published relationship intact
   */
  private function retainsPublishedRelationship(FacilityHierarchyNode $node, ?FacilityHierarchyNode $original): bool
  {
    return null !== $original
      && 'published' === $original->publicationState
      && 'published' === $node->publicationState
      && $original->type === $node->type
      && $original->parentFacilityId === $node->parentFacilityId
      && !('archived' === $original->status && 'active' === $node->status);
  }

  /**
   * Method nodeIssues.
   *
   * @since 1.0.0
   *
   * @param array<string, FacilityHierarchyNode> $graph scoped rows
   *
   * @return list<string> stable public codes
   */
  private function nodeIssues(FacilityHierarchyNode $node, array $graph): array
  {
    $current = $node;
    $seen = [];
    while (true) {
      if (isset($seen[$current->id])) {
        return ['cycle'];
      }
      $seen[$current->id] = true;
      if (count($seen) > $this->maxDepth) {
        return ['depth_exceeded'];
      }
      $parent = null === $current->parentFacilityId ? null : ($graph[$current->parentFacilityId] ?? null);
      if (null !== $current->parentFacilityId && null === $parent) {
        return ['missing_parent'];
      }
      $type = FacilityType::tryFrom($current->type);
      $parentType = null === $parent ? null : FacilityType::tryFrom($parent->type);
      if (null === $type || !FacilityHierarchyPolicy::allowsParent($type, $parentType)) {
        return [$current->id === $node->id ? 'invalid_parent_type' : 'invalid_ancestor'];
      }
      if (null === $parent) {
        return [];
      }
      if ('active' !== $parent->status) {
        return ['invalid_ancestor'];
      }
      if ('published' !== $parent->publicationState
        && ('draft' !== $current->publicationState || null === $current->interventionId || $current->interventionId !== $parent->interventionId)) {
        return ['unpublished_parent'];
      }
      $current = $parent;
    }
  }

  /**
   * Method allowsInGraph.
   *
   * @since 1.0.0
   *
   * @param array<string, FacilityHierarchyNode> $graph scoped persisted rows
   */
  private function allowsInGraph(array $graph, string $type, ?string $parentId, ?string $facilityId, ?int $height = null, ?string $interventionId = null): bool
  {
    if (null !== $facilityId && !isset($graph[$facilityId])) {
      return false;
    }
    $previous = null === $facilityId ? null : $graph[$facilityId];
    if (null !== $previous && null !== $interventionId && 'draft' === $previous->publicationState && $interventionId !== $previous->interventionId) {
      return false;
    }
    $node = new FacilityHierarchyNode(
      $facilityId ?? '__new__',
      $type,
      $parentId,
      $previous->status ?? 'active',
      $previous->publicationState ?? (null === $interventionId ? 'published' : 'draft'),
      null === $previous ? $interventionId : $previous->interventionId,
    );
    $graph[$node->id] = $node;

    try {
      $this->assertNode($node, $graph);
      $this->assertDepth($node, $graph, $height ?? $this->subtreeHeight($node->id, $graph));
    } catch (FacilityHierarchyException) {
      return false;
    }

    return true;
  }

  /**
   * Method assertNode.
   *
   * @since 1.0.0
   *
   * @param array<string, FacilityHierarchyNode> $graph proposed final rows keyed by identifier
   */
  private function assertNode(FacilityHierarchyNode $node, array $graph): void
  {
    $seen = [];
    $current = $node;
    while (true) {
      if (array_key_exists($current->id, $seen)) {
        throw FacilityHierarchyException::hierarchyCycleDetected();
      }
      $seen[$current->id] = true;
      if (count($seen) > $this->maxDepth) {
        throw FacilityHierarchyException::maxDepthExceeded($this->maxDepth);
      }
      $parent = null === $current->parentFacilityId ? null : ($graph[$current->parentFacilityId] ?? null);
      if (null !== $current->parentFacilityId && null === $parent) {
        throw FacilityHierarchyException::parentUnavailable();
      }
      FacilityHierarchyPolicy::assertParent($this->type($current->type), null === $parent ? null : $this->type($parent->type));
      if (null === $parent) {
        return;
      }
      if ('active' !== $parent->status) {
        throw FacilityHierarchyException::parentInactive();
      }
      if ('published' !== $parent->publicationState
        && ('draft' !== $current->publicationState || null === $current->interventionId || $current->interventionId !== $parent->interventionId)) {
        throw FacilityHierarchyException::parentPublicationIncompatible();
      }
      $current = $parent;
    }
  }

  /**
   * Method assertSubtreeDepth.
   *
   * @since 1.0.0
   *
   * @param array<string, FacilityHierarchyNode> $graph proposed final rows
   */
  private function assertSubtreeDepth(FacilityHierarchyNode $node, array $graph): void
  {
    $this->assertDepth($node, $graph, $this->subtreeHeight($node->id, $graph));
  }

  /**
   * Method assertDepth.
   *
   * @since 1.0.0
   *
   * @param array<string, FacilityHierarchyNode> $graph validated ancestor graph
   */
  private function assertDepth(FacilityHierarchyNode $node, array $graph, int $height): void
  {
    $depth = 1;
    while (null !== $node->parentFacilityId) {
      ++$depth;
      $node = $graph[$node->parentFacilityId];
    }
    if ($depth + $height > $this->maxDepth) {
      throw FacilityHierarchyException::maxDepthExceeded($this->maxDepth);
    }
  }

  /**
   * Method subtreeHeight.
   *
   * @since 1.0.0
   *
   * @param array<string, FacilityHierarchyNode> $graph scoped rows
   *
   * @return int greatest descendant distance, including historical and draft children
   */
  private function subtreeHeight(string $id, array $graph): int
  {
    $height = 0;
    foreach ($graph as $descendant) {
      $current = $descendant;
      $seen = [];
      $distance = 0;
      while (null !== $current->parentFacilityId && !isset($seen[$current->id])) {
        $seen[$current->id] = true;
        ++$distance;
        if ($current->parentFacilityId === $id) {
          // Only depth is checked for unchanged descendant edges. Their legacy
          // taxonomy does not block repairing the root's own relationship.
          if ($distance > $height) {
            $height = $distance;
          }

          break;
        }
        $current = $graph[$current->parentFacilityId] ?? null;
        if (null === $current) {
          break;
        }
      }
    }

    return $height;
  }

  /**
   * Method type.
   *
   * @since 1.0.0
   */
  private function type(string $type): FacilityType
  {
    return FacilityType::tryFrom($type) ?? throw FacilityHierarchyException::unsupportedType($type);
  }
  // #endregion
}
