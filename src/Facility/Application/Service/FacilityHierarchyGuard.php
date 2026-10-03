<?php

declare(strict_types=1);

namespace Facility\Application\Service;

use Facility\Application\Contract\Hierarchy\FacilityHierarchyNode;
use Facility\Application\Port\Inbound\FacilityHierarchyPort;
use Facility\Application\Port\Outbound\FacilityHierarchySnapshotPort;
use Facility\Domain\Exception\FacilityHierarchyException;
use Facility\Domain\ValueObject\{FacilityHierarchyPolicy, FacilityType};
use Symfony\Component\DependencyInjection\Attribute\Autowire;

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
        $this->assertDepth($node, $graph, $this->subtreeHeight($node->id, $graph));
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
    $issue = null;
    while (null !== $current && null === $issue) {
      $issue = $this->traversalIssue($current, $seen);
      if (null !== $issue) {
        break;
      }
      $seen[$current->id] = true;
      $parent = null === $current->parentFacilityId ? null : ($graph[$current->parentFacilityId] ?? null);
      $issue = $this->relationshipIssue($current, $parent);
      if ('invalid_parent_type' === $issue && $current->id !== $node->id) {
        $issue = 'invalid_ancestor';
      }
      $current = $parent;
    }

    return null === $issue ? [] : [$issue];
  }

  /**
   * Method traversalIssue.
   *
   * Gives cycles precedence over depth overflow before another ancestor is visited.
   *
   * @access private
   *
   * @param FacilityHierarchyNode $node the next ancestor to visit
   * @param array<string, true> $seen ancestors already visited
   *
   * @return ?string the bounded-walk diagnostic without resource identifiers
   */
  private function traversalIssue(FacilityHierarchyNode $node, array $seen): ?string
  {
    return match (true) {
      isset($seen[$node->id]) => 'cycle',
      count($seen) + 1 > $this->maxDepth => 'depth_exceeded',
      default => null,
    };
  }

  /**
   * Method relationshipIssue.
   *
   * Separates an unavailable scoped parent from an incompatible taxonomy.
   *
   * @access private
   *
   * @param FacilityHierarchyNode $node the relationship being inspected
   * @param ?FacilityHierarchyNode $parent its organization-scoped parent, if present
   *
   * @return ?string the public relationship diagnostic without parent identifiers
   */
  private function relationshipIssue(FacilityHierarchyNode $node, ?FacilityHierarchyNode $parent): ?string
  {
    if (null !== $node->parentFacilityId && null === $parent) {
      return 'missing_parent';
    }
    $type = FacilityType::tryFrom($node->type);
    $parentType = null === $parent ? null : FacilityType::tryFrom($parent->type);
    if (null === $type || !FacilityHierarchyPolicy::allowsParent($type, $parentType)) {
      return 'invalid_parent_type';
    }

    return $this->parentAvailabilityIssue($node, $parent);
  }

  /**
   * Method parentAvailabilityIssue.
   *
   * Checks lifecycle before publication scope after taxonomy has been validated.
   *
   * @access private
   *
   * @param FacilityHierarchyNode $node the child whose parent is being inspected
   * @param ?FacilityHierarchyNode $parent its compatible parent, absent for a root
   *
   * @return ?string the lifecycle or publication diagnostic
   */
  private function parentAvailabilityIssue(FacilityHierarchyNode $node, ?FacilityHierarchyNode $parent): ?string
  {
    if (null === $parent) {
      return null;
    }

    return match (true) {
      'active' !== $parent->status => 'invalid_ancestor',
      !$this->hasCompatiblePublicationParent($node, $parent) => 'unpublished_parent',
      default => null,
    };
  }

  /**
   * Method hasCompatiblePublicationParent.
   *
   * Published children require published parents; draft children may retain their own intervention's parent.
   *
   * @access private
   *
   * @param FacilityHierarchyNode $node the child publication state
   * @param FacilityHierarchyNode $parent the parent publication scope
   *
   * @return bool whether the two publication scopes permit the relationship
   */
  private function hasCompatiblePublicationParent(FacilityHierarchyNode $node, FacilityHierarchyNode $parent): bool
  {
    return 'published' === $parent->publicationState
      || ('draft' === $node->publicationState && null !== $node->interventionId && $node->interventionId === $parent->interventionId);
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
    $previous = null === $facilityId ? null : ($graph[$facilityId] ?? null);
    $outsideDraftContext = null !== $previous
      && null !== $interventionId
      && 'draft' === $previous->publicationState
      && $interventionId !== $previous->interventionId;
    if ((null !== $facilityId && null === $previous) || $outsideDraftContext) {
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
    while (null !== $current) {
      $this->assertTraversalAllowed($current, $seen);
      $seen[$current->id] = true;
      $current = $this->validatedParent($current, $graph);
    }
  }

  /**
   * Method assertTraversalAllowed.
   *
   * Applies the same ordered cycle/depth decisions as descriptive diagnostics.
   *
   * @access private
   *
   * @param FacilityHierarchyNode $node the next ancestor to visit
   * @param array<string, true> $seen ancestors already visited
   *
   * @return void
   */
  private function assertTraversalAllowed(FacilityHierarchyNode $node, array $seen): void
  {
    $issue = $this->traversalIssue($node, $seen);
    if ('cycle' === $issue) {
      throw FacilityHierarchyException::hierarchyCycleDetected();
    }
    if ('depth_exceeded' === $issue) {
      throw FacilityHierarchyException::maxDepthExceeded($this->maxDepth);
    }
  }

  /**
   * Method validatedParent.
   *
   * Validates one strict relationship before advancing to the next ancestor.
   *
   * @access private
   *
   * @param FacilityHierarchyNode $node the relationship being validated
   * @param array<string, FacilityHierarchyNode> $graph the final organization graph
   *
   * @return ?FacilityHierarchyNode the next ancestor, absent for a valid root
   */
  private function validatedParent(FacilityHierarchyNode $node, array $graph): ?FacilityHierarchyNode
  {
    $parent = null === $node->parentFacilityId ? null : ($graph[$node->parentFacilityId] ?? null);
    if (null !== $node->parentFacilityId && null === $parent) {
      throw FacilityHierarchyException::parentUnavailable();
    }
    FacilityHierarchyPolicy::assertParent($this->type($node->type), null === $parent ? null : $this->type($parent->type));
    if (null !== $parent) {
      $this->assertParentAvailability($node, $parent);
    }

    return $parent;
  }

  /**
   * Method assertParentAvailability.
   *
   * Lifecycle failures precede publication-scope failures for strict relationships.
   *
   * @access private
   *
   * @param FacilityHierarchyNode $node the child publication state
   * @param FacilityHierarchyNode $parent the resolved compatible parent
   *
   * @return void
   */
  private function assertParentAvailability(FacilityHierarchyNode $node, FacilityHierarchyNode $parent): void
  {
    if ('active' !== $parent->status) {
      throw FacilityHierarchyException::parentInactive();
    }
    if (!$this->hasCompatiblePublicationParent($node, $parent)) {
      throw FacilityHierarchyException::parentPublicationIncompatible();
    }
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
