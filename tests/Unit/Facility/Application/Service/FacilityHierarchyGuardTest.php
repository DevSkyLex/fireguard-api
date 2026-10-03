<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Application\Service;

use Facility\Application\Contract\Hierarchy\FacilityHierarchyNode;
use Facility\Application\Port\Outbound\FacilityHierarchySnapshotPort;
use Facility\Application\Service\FacilityHierarchyGuard;
use Facility\Domain\Exception\FacilityHierarchyException;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test FacilityHierarchyGuardTest.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(FacilityHierarchyGuard::class)]
final class FacilityHierarchyGuardTest extends TestCase
{
  // #region Tests
  #[Test]
  public function testPreparationCandidatesIncludeOnlyCompatibleDraftsAndPublishedParents(): void
  {
    $guard = $this->guard([
      new FacilityHierarchyNode('published', 'site', null),
      new FacilityHierarchyNode('draft', 'site', null, publicationState: 'draft', interventionId: 'current'),
      new FacilityHierarchyNode('other', 'site', null, publicationState: 'draft', interventionId: 'other'),
      new FacilityHierarchyNode('inactive', 'site', null, status: 'archived', publicationState: 'draft', interventionId: 'current'),
    ]);
    self::assertSame(['published', 'draft'], $guard->eligibleParentIds('organization', 'building', interventionId: 'current'));
    self::assertSame(['published'], $guard->eligibleParentIds('organization', 'building'));
  }

  #[Test]
  public function testMovingDraftRetainsPublicationStateAndCannotBeRetargetedToAnotherIntervention(): void
  {
    $guard = $this->guard([
      new FacilityHierarchyNode('published', 'site', null),
      new FacilityHierarchyNode('draft', 'site', null, publicationState: 'draft', interventionId: 'current'),
      new FacilityHierarchyNode('building', 'building', 'published', publicationState: 'draft', interventionId: 'current'),
      new FacilityHierarchyNode('publishedBuilding', 'building', 'published'),
    ]);
    self::assertTrue($guard->allowsParent('organization', 'building', 'draft', 'building', 'current'));
    self::assertFalse($guard->allowsParent('organization', 'building', 'draft', 'publishedBuilding', 'current'));
    self::assertFalse($guard->allowsParent('organization', 'building', 'draft', 'building', 'other'));
    self::assertSame(['published', 'draft'], $guard->eligibleParentIds('organization', 'building', 'building', 'current'));
  }

  /**
   * Method testMergedImportNodesValidateBeforeAnyParentIsPersisted.
   *
   * @since 1.0.0
   */
  #[Test]
  public function testMergedImportNodesValidateBeforeAnyParentIsPersisted(): void
  {
    $this->expectNotToPerformAssertions();
    $this->guard([])->assertGraph('organization', [
      new FacilityHierarchyNode('floor', 'floor', 'building'),
      new FacilityHierarchyNode('building', 'building', 'site'),
      new FacilityHierarchyNode('site', 'site', null),
      new FacilityHierarchyNode('outside', 'area', 'site'),
    ]);
  }

  /**
   * Method testLegacyInvalidBranchesDoNotBlockExplicitRootRepairs.
   *
   * @since 1.0.0
   */
  #[Test]
  public function testLegacyInvalidBranchesDoNotBlockExplicitRootRepairs(): void
  {
    $this->expectNotToPerformAssertions();
    $guard = $this->guard([
      new FacilityHierarchyNode('site', 'site', null),
      new FacilityHierarchyNode('building', 'building', null),
      new FacilityHierarchyNode('unrelated', 'floor', null),
    ]);
    $guard->assertGraph('organization', [new FacilityHierarchyNode('building', 'building', 'site')]);
  }

  /**
   * Method testAValidDirectParentWithInvalidAncestryIsRejected.
   *
   * @since 1.0.0
   */
  #[Test]
  public function testAValidDirectParentWithInvalidAncestryIsRejected(): void
  {
    $guard = $this->guard([new FacilityHierarchyNode('building', 'building', null)]);
    $this->expectException(FacilityHierarchyException::class);
    $guard->assertGraph('organization', [new FacilityHierarchyNode('floor', 'floor', 'building')]);
  }

  /**
   * Method testPublishedNodesCannotDependOnDraftParents.
   *
   * @since 1.0.0
   */
  #[Test]
  public function testPublishedNodesCannotDependOnDraftParents(): void
  {
    $guard = $this->guard([new FacilityHierarchyNode('site', 'site', null, publicationState: 'draft', interventionId: 'intervention')]);
    $this->expectException(FacilityHierarchyException::class);
    $this->expectExceptionMessage('Published facilities require published parents');
    $guard->assertGraph('organization', [new FacilityHierarchyNode('building', 'building', 'site')]);
  }

  /**
   * Method testDraftsWithinTheSameInterventionCanReferenceEachOther.
   *
   * @since 1.0.0
   */
  #[Test]
  public function testDraftsWithinTheSameInterventionCanReferenceEachOther(): void
  {
    $this->expectNotToPerformAssertions();
    $this->guard([])->assertGraph('organization', [
      new FacilityHierarchyNode('site', 'site', null, publicationState: 'draft', interventionId: 'intervention'),
      new FacilityHierarchyNode('building', 'building', 'site', publicationState: 'draft', interventionId: 'intervention'),
    ]);
  }

  /**
   * Method testDraftsFromDifferentInterventionsCannotReferenceEachOther.
   *
   * @since 1.0.0
   */
  #[Test]
  public function testDraftsFromDifferentInterventionsCannotReferenceEachOther(): void
  {
    $guard = $this->guard([new FacilityHierarchyNode('site', 'site', null, publicationState: 'draft', interventionId: 'first')]);
    $this->expectException(FacilityHierarchyException::class);
    $guard->assertGraph('organization', [new FacilityHierarchyNode('building', 'building', 'site', publicationState: 'draft', interventionId: 'second')]);
  }

  /**
   * Method testPublicationValidatesTheFinalMergedGraph.
   *
   * @since 1.0.0
   */
  #[Test]
  public function testPublicationValidatesTheFinalMergedGraph(): void
  {
    $this->expectNotToPerformAssertions();
    $guard = $this->guard([
      new FacilityHierarchyNode('site', 'site', null, publicationState: 'draft', interventionId: 'intervention'),
      new FacilityHierarchyNode('building', 'building', 'site', publicationState: 'draft', interventionId: 'intervention'),
    ]);
    $guard->assertGraph('organization', [new FacilityHierarchyNode('building', 'building', 'site'), new FacilityHierarchyNode('site', 'site', null)]);
  }

  /**
   * Method testPublicationRetainsExistingEdgesWhenArchivingAWholeBranch.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testPublicationRetainsExistingEdgesWhenArchivingAWholeBranch(): void
  {
    $this->expectNotToPerformAssertions();
    $original = [new FacilityHierarchyNode('site', 'site', null), new FacilityHierarchyNode('zone', 'zone', 'site')];
    $final = [new FacilityHierarchyNode('zone', 'zone', 'site', 'archived'), new FacilityHierarchyNode('site', 'site', null, 'archived')];
    $baseline = $this->index($original);
    $this->guard($original)->assertGraph('organization', $final, $baseline);
    $this->guard($final)->assertGraph('organization', $final, $baseline);
  }

  /**
   * Method testPublicationRetainsLegacyTaxonomyDuringDescriptiveEdits.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testPublicationRetainsLegacyTaxonomyDuringDescriptiveEdits(): void
  {
    $this->expectNotToPerformAssertions();
    $nodes = [new FacilityHierarchyNode('legacy', 'building', null)];
    $this->guard($nodes)->assertGraph('organization', $nodes, $this->index($nodes));
  }

  /**
   * Method testPublicationStillRejectsANewRelationshipToAnArchivedParent.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testPublicationStillRejectsANewRelationshipToAnArchivedParent(): void
  {
    $original = [
      new FacilityHierarchyNode('site', 'site', null),
      new FacilityHierarchyNode('destination', 'site', null),
      new FacilityHierarchyNode('zone', 'zone', 'site'),
    ];
    $this->expectException(FacilityHierarchyException::class);
    $this->expectExceptionMessage('The parent facility must be active.');
    $this->guard($original)->assertGraph('organization', [
      new FacilityHierarchyNode('destination', 'site', null, 'archived'),
      new FacilityHierarchyNode('zone', 'zone', 'destination', 'archived'),
    ], $this->index($original));
  }

  /**
   * Method testFinalValidationUsesTheOriginalRelationshipAfterWritesHavePersisted.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testFinalValidationUsesTheOriginalRelationshipAfterWritesHavePersisted(): void
  {
    $original = [new FacilityHierarchyNode('site', 'site', null), new FacilityHierarchyNode('zone', 'zone', 'site')];
    $final = [new FacilityHierarchyNode('site', 'site', null), new FacilityHierarchyNode('zone', 'zone', 'zone')];
    $this->expectException(FacilityHierarchyException::class);
    $this->expectExceptionMessage('hierarchy cycle detected');
    $this->guard($final)->assertGraph('organization', $final, $this->index($original));
  }

  /**
   * Method testFinalValidationStillChecksChildrenAgainstTheOriginalParentType.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testFinalValidationStillChecksChildrenAgainstTheOriginalParentType(): void
  {
    $original = [
      new FacilityHierarchyNode('site', 'site', null),
      new FacilityHierarchyNode('building', 'building', 'site'),
      new FacilityHierarchyNode('floor', 'floor', 'building'),
    ];
    $final = [
      new FacilityHierarchyNode('site', 'site', null),
      new FacilityHierarchyNode('building', 'zone', 'site'),
      new FacilityHierarchyNode('floor', 'floor', 'building'),
    ];
    $this->expectException(FacilityHierarchyException::class);
    $this->expectExceptionMessage('Facility type "floor" cannot have parent type "zone"');
    $this->guard($final)->assertGraph('organization', [$final[1]], $this->index($original));
  }

  /**
   * Method testDraftPublicationCannotRetainAnInvalidDraftRelationship.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testDraftPublicationCannotRetainAnInvalidDraftRelationship(): void
  {
    $original = [new FacilityHierarchyNode('building', 'building', null, publicationState: 'draft', interventionId: 'intervention')];
    $this->expectException(FacilityHierarchyException::class);
    $this->expectExceptionMessage('Facility type "building" cannot have parent type "none"');
    $this->guard($original)->assertGraph('organization', [new FacilityHierarchyNode('building', 'building', null)], $this->index($original));
  }

  /**
   * Method testRestorationStillRequiresValidAncestry.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testRestorationStillRequiresValidAncestry(): void
  {
    $original = [new FacilityHierarchyNode('site', 'site', null, 'archived'), new FacilityHierarchyNode('zone', 'zone', 'site', 'archived')];
    $this->expectException(FacilityHierarchyException::class);
    $this->expectExceptionMessage('The parent facility must be active.');
    $this->guard($original)->assertGraph('organization', [new FacilityHierarchyNode('zone', 'zone', 'site')], $this->index($original));
  }

  /**
   * Method testATypeChangeChecksExistingChildren.
   *
   * @since 1.0.0
   */
  #[Test]
  public function testATypeChangeChecksExistingChildren(): void
  {
    $guard = $this->guard([
      new FacilityHierarchyNode('site', 'site', null),
      new FacilityHierarchyNode('building', 'building', 'site'),
      new FacilityHierarchyNode('floor', 'floor', 'building'),
    ]);
    $this->expectException(FacilityHierarchyException::class);
    $this->expectExceptionMessage('Facility type "floor" cannot have parent type "zone"');
    $guard->assertGraph('organization', [new FacilityHierarchyNode('building', 'zone', 'site')]);
  }

  /**
   * Method testASecondMoveObservesTheFirstCommittedEdgeAndRejectsACycle.
   *
   * @since 1.0.0
   */
  #[Test]
  public function testASecondMoveObservesTheFirstCommittedEdgeAndRejectsACycle(): void
  {
    $guard = $this->guard([
      new FacilityHierarchyNode('site', 'site', null),
      new FacilityHierarchyNode('first', 'zone', 'site'),
      new FacilityHierarchyNode('second', 'zone', 'first'),
    ]);
    $this->expectException(FacilityHierarchyException::class);
    $this->expectExceptionMessage('hierarchy cycle detected');
    $guard->assertGraph('organization', [new FacilityHierarchyNode('first', 'zone', 'second')]);
  }

  /**
   * Method testMovingASubtreeCountsEveryDescendantForDepth.
   *
   * @since 1.0.0
   */
  #[Test]
  public function testMovingASubtreeCountsEveryDescendantForDepth(): void
  {
    $guard = $this->guard([
      new FacilityHierarchyNode('site', 'site', null),
      new FacilityHierarchyNode('building', 'building', 'site'),
      new FacilityHierarchyNode('branch', 'zone', 'site'),
      new FacilityHierarchyNode('leaf', 'zone', 'branch'),
    ], 3);
    $this->expectException(FacilityHierarchyException::class);
    $guard->assertGraph('organization', [new FacilityHierarchyNode('branch', 'zone', 'building')]);
  }

  /**
   * Method testPickerUsesOneSnapshotAndFiltersInvalidAncestryAndDescendants.
   *
   * @since 1.0.0
   */
  #[Test]
  public function testPickerUsesOneSnapshotAndFiltersInvalidAncestryAndDescendants(): void
  {
    $nodes = [
      new FacilityHierarchyNode('site', 'site', null),
      new FacilityHierarchyNode('building', 'building', 'site'),
      new FacilityHierarchyNode('branch', 'zone', 'site'),
      new FacilityHierarchyNode('leaf', 'zone', 'branch'),
      new FacilityHierarchyNode('legacy', 'building', null),
      new FacilityHierarchyNode('draft', 'site', null, publicationState: 'draft', interventionId: 'intervention'),
    ];
    $snapshot = $this->createMock(FacilityHierarchySnapshotPort::class);
    $snapshot->expects(self::once())->method('load')->with('organization')->willReturn($this->index($nodes));
    self::assertSame(['site', 'building'], new FacilityHierarchyGuard($snapshot)->eligibleParentIds('organization', 'zone', 'branch'));
  }

  /**
   * Method testMissingOrForeignParentsHaveAnOpaqueDiagnostic.
   *
   * @since 1.0.0
   */
  #[Test]
  public function testMissingOrForeignParentsHaveAnOpaqueDiagnostic(): void
  {
    self::assertSame(['building' => ['missing_parent']], $this->guard([
      new FacilityHierarchyNode('building', 'building', 'foreign'),
    ])->issuesFor('organization', ['building', 'foreign']));
  }

  /**
   * Method testDiagnosticsPreserveAncestryFailurePrecedenceWithoutExposingParentIds.
   *
   * @access public
   *
   * @param list<FacilityHierarchyNode> $nodes the organization-scoped historical graph
   * @param int $maxDepth the configured ancestry cap
   * @param list<string> $expected the public diagnostic codes
   *
   * @return void
   */
  #[Test]
  #[DataProvider('diagnosticCases')]
  public function testDiagnosticsPreserveAncestryFailurePrecedenceWithoutExposingParentIds(array $nodes, int $maxDepth, array $expected): void
  {
    self::assertSame(['node' => $expected], $this->guard($nodes, $maxDepth)->issuesFor('organization', ['node', 'unavailable-private-parent']));
  }

  /**
   * Method testAnUnknownFacilityCannotBorrowThePublishedParentContext.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testAnUnknownFacilityCannotBorrowThePublishedParentContext(): void
  {
    $guard = $this->guard([new FacilityHierarchyNode('site', 'site', null)]);
    self::assertFalse($guard->allowsParent('organization', 'building', 'site', 'unknown', 'intervention'));
    self::assertSame([], $guard->eligibleParentIds('organization', 'building', 'unknown', 'intervention'));
  }

  /**
   * Method testArchivedParentsAreNotEligible.
   *
   * @since 1.0.0
   */
  #[Test]
  public function testArchivedParentsAreNotEligible(): void
  {
    self::assertFalse($this->guard([new FacilityHierarchyNode('site', 'site', null, 'archived')])->allowsParent('organization', 'building', 'site'));
  }

  /**
   * Method testTheLockDelegatesToTheOwningPersistencePort.
   *
   * @since 1.0.0
   */
  #[Test]
  public function testTheLockDelegatesToTheOwningPersistencePort(): void
  {
    $snapshot = $this->createMock(FacilityHierarchySnapshotPort::class);
    $snapshot->expects(self::once())->method('lock')->with('organization');
    new FacilityHierarchyGuard($snapshot)->lock('organization');
  }
  // #endregion

  // #region Helpers
  /**
   * Method diagnosticCases.
   *
   * Covers public diagnostic precedence across malformed historical relationships.
   *
   * @access public
   *
   * @return iterable<string, array{list<FacilityHierarchyNode>, int, list<string>}> scoped graphs and their public diagnostics
   */
  public static function diagnosticCases(): iterable
  {
    return [
      'valid site root' => [[new FacilityHierarchyNode('node', 'site', null)], 8, []],
      'cycle precedes depth' => [[new FacilityHierarchyNode('node', 'zone', 'node')], 1, ['cycle']],
      'depth before visiting the next ancestor' => [[new FacilityHierarchyNode('node', 'building', 'site'), new FacilityHierarchyNode('site', 'site', null)], 1, ['depth_exceeded']],
      'invalid direct taxonomy' => [[new FacilityHierarchyNode('node', 'building', null)], 8, ['invalid_parent_type']],
      'invalid ancestor taxonomy' => [[new FacilityHierarchyNode('node', 'zone', 'building'), new FacilityHierarchyNode('building', 'building', null)], 8, ['invalid_ancestor']],
      'unavailable scoped parent' => [[new FacilityHierarchyNode('node', 'zone', 'unavailable-private-parent')], 8, ['missing_parent']],
      'unavailable ancestor' => [[new FacilityHierarchyNode('node', 'zone', 'area'), new FacilityHierarchyNode('area', 'area', 'unavailable-private-parent')], 8, ['missing_parent']],
      'inactive parent precedes publication mismatch' => [[new FacilityHierarchyNode('node', 'zone', 'site'), new FacilityHierarchyNode('site', 'site', null, 'archived', 'draft', 'other')], 8, ['invalid_ancestor']],
      'published child of a draft' => [[new FacilityHierarchyNode('node', 'zone', 'site'), new FacilityHierarchyNode('site', 'site', null, publicationState: 'draft', interventionId: 'current')], 8, ['unpublished_parent']],
      'same intervention drafts' => [[new FacilityHierarchyNode('node', 'zone', 'site', publicationState: 'draft', interventionId: 'current'), new FacilityHierarchyNode('site', 'site', null, publicationState: 'draft', interventionId: 'current')], 8, []],
      'different intervention drafts' => [[new FacilityHierarchyNode('node', 'zone', 'site', publicationState: 'draft', interventionId: 'current'), new FacilityHierarchyNode('site', 'site', null, publicationState: 'draft', interventionId: 'other')], 8, ['unpublished_parent']],
      'unsupported child type' => [[new FacilityHierarchyNode('node', 'unknown', null)], 8, ['invalid_parent_type']],
      'unsupported immediate parent type' => [[new FacilityHierarchyNode('node', 'zone', 'parent'), new FacilityHierarchyNode('parent', 'unknown', null)], 8, ['invalid_parent_type']],
    ];
  }

  /**
   * Method guard.
   *
   * @param list<FacilityHierarchyNode> $nodes persisted rows
   */
  private function guard(array $nodes, int $maxDepth = 8): FacilityHierarchyGuard
  {
    $snapshot = $this->createStub(FacilityHierarchySnapshotPort::class);
    $snapshot->method('load')->willReturn($this->index($nodes));

    return new FacilityHierarchyGuard($snapshot, $maxDepth);
  }

  /**
   * Method index.
   *
   * @param list<FacilityHierarchyNode> $nodes persisted rows
   *
   * @return array<string, FacilityHierarchyNode> rows keyed by identifier
   */
  private function index(array $nodes): array
  {
    $graph = [];
    foreach ($nodes as $node) {
      $graph[$node->id] = $node;
    }

    return $graph;
  }
  // #endregion
}
