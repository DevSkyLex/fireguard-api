<?php

declare(strict_types=1);

namespace Tests\Integration\Facility\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Facility\Domain\Model\Facility\Facility;
use Facility\Domain\ValueObject\{FacilityId, FacilityName, FacilityOrganizationId, FacilityType, PlanGeometry};
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Facility\Infrastructure\Persistence\Doctrine\Repository\FacilityRepository;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_column;

#[CoversClass(FacilityRepository::class)]
final class FacilityRepositoryTest extends KernelTestCase
{
  private EntityManagerInterface $entityManager;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->removeOrganization('550e8400-e29b-41d4-a716-446655443000');
    $this->removeOrganization('550e8400-e29b-41d4-a716-446655443001');
  }

  protected function tearDown(): void
  {
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testFindByOrganizationIdCanReturnOnlyVisibleRoots(): void
  {
    $organization = $this->createOrganization('550e8400-e29b-41d4-a716-446655443000', 'facility-repository-tree-a');
    $otherOrganization = $this->createOrganization('550e8400-e29b-41d4-a716-446655443001', 'facility-repository-tree-b');

    $root = $this->createFacility('550e8400-e29b-41d4-a716-446655443010', $organization, null, 'Root A');
    $this->createFacility('550e8400-e29b-41d4-a716-446655443011', $organization, $root, 'Child A');
    $this->createFacility('550e8400-e29b-41d4-a716-446655443012', $organization, null, 'Archived Root', 'archived');
    $this->createFacility('550e8400-e29b-41d4-a716-446655443013', $otherOrganization, null, 'Other Root');

    $this->entityManager->flush();
    $this->entityManager->clear();

    $repository = new FacilityRepository($this->entityManager);

    $roots = $repository->findByOrganizationId(
      organizationId: new FacilityOrganizationId($organization->id),
      limit: 20,
      offset: 0,
      rootsOnly: true,
    );

    self::assertCount(1, $roots);
    self::assertSame('550e8400-e29b-41d4-a716-446655443010', (string) $roots[0]->id());
    self::assertSame(1, $repository->countByOrganizationId(
      organizationId: new FacilityOrganizationId($organization->id),
      rootsOnly: true,
    ));
    self::assertSame(2, $repository->countByOrganizationId(
      organizationId: new FacilityOrganizationId($organization->id),
      includeArchived: true,
      rootsOnly: true,
    ));
  }

  #[Test]
  public function testFindByOrganizationIdFiltersByHasCoordinates(): void
  {
    $organization = $this->createOrganization('550e8400-e29b-41d4-a716-446655443000', 'facility-repository-coordinates-a');

    $placed = $this->createFacility(
      '550e8400-e29b-41d4-a716-446655443014',
      $organization,
      null,
      'Placed Site',
      latitude: 48.8566,
      longitude: 2.3522,
    );
    $this->createFacility('550e8400-e29b-41d4-a716-446655443015', $organization, null, 'Unplaced Site');

    $this->entityManager->flush();
    $this->entityManager->clear();

    $repository = new FacilityRepository($this->entityManager);
    $organizationId = new FacilityOrganizationId($organization->id);

    $withCoordinates = $repository->findByOrganizationId(
      organizationId: $organizationId,
      limit: 20,
      offset: 0,
      hasCoordinates: true,
    );
    self::assertCount(1, $withCoordinates);
    self::assertSame($placed->id, (string) $withCoordinates[0]->id());
    self::assertSame(1, $repository->countByOrganizationId(
      organizationId: $organizationId,
      hasCoordinates: true,
    ));

    $withoutCoordinates = $repository->findByOrganizationId(
      organizationId: $organizationId,
      limit: 20,
      offset: 0,
      hasCoordinates: false,
    );
    self::assertCount(1, $withoutCoordinates);
    self::assertSame('550e8400-e29b-41d4-a716-446655443015', (string) $withoutCoordinates[0]->id());
    self::assertSame(1, $repository->countByOrganizationId(
      organizationId: $organizationId,
      hasCoordinates: false,
    ));

    $all = $repository->findByOrganizationId(
      organizationId: $organizationId,
      limit: 20,
      offset: 0,
    );
    self::assertCount(2, $all);
    self::assertSame(2, $repository->countByOrganizationId(organizationId: $organizationId));
  }

  #[Test]
  public function testFindChildrenPaginatesAndCountsMatchingChildren(): void
  {
    $organization = $this->createOrganization('550e8400-e29b-41d4-a716-446655443000', 'facility-repository-children-a');
    $parent = $this->createFacility('550e8400-e29b-41d4-a716-446655443020', $organization, null, 'Parent');
    $this->createFacility('550e8400-e29b-41d4-a716-446655443021', $organization, $parent, 'Alpha');
    $this->createFacility('550e8400-e29b-41d4-a716-446655443022', $organization, $parent, 'Bravo');
    $this->createFacility('550e8400-e29b-41d4-a716-446655443023', $organization, $parent, 'Charlie');
    $this->createFacility('550e8400-e29b-41d4-a716-446655443024', $organization, $parent, 'Archived', 'archived');

    $this->entityManager->flush();
    $this->entityManager->clear();

    $repository = new FacilityRepository($this->entityManager);

    $children = $repository->findChildren(
      organizationId: new FacilityOrganizationId($organization->id),
      facilityId: new FacilityId($parent->id),
      limit: 1,
      offset: 1,
    );

    self::assertCount(1, $children);
    self::assertSame('Bravo', (string) $children[0]->name());
    self::assertSame(3, $repository->countChildren(new FacilityOrganizationId($organization->id), new FacilityId($parent->id)));
    self::assertSame(4, $repository->countChildren(new FacilityOrganizationId($organization->id), new FacilityId($parent->id), includeArchived: true));
  }

  #[Test]
  public function testCountChildrenByParentIdsGroupsVisibleChildren(): void
  {
    $organization = $this->createOrganization('550e8400-e29b-41d4-a716-446655443000', 'facility-repository-counts-a');
    $otherOrganization = $this->createOrganization('550e8400-e29b-41d4-a716-446655443001', 'facility-repository-counts-b');
    $parentA = $this->createFacility('550e8400-e29b-41d4-a716-446655443030', $organization, null, 'Parent A');
    $parentB = $this->createFacility('550e8400-e29b-41d4-a716-446655443031', $organization, null, 'Parent B');
    $otherParent = $this->createFacility('550e8400-e29b-41d4-a716-446655443032', $otherOrganization, null, 'Other Parent');

    $this->createFacility('550e8400-e29b-41d4-a716-446655443033', $organization, $parentA, 'A1');
    $this->createFacility('550e8400-e29b-41d4-a716-446655443034', $organization, $parentA, 'A2 Archived', 'archived');
    $this->createFacility('550e8400-e29b-41d4-a716-446655443035', $organization, $parentB, 'B1');
    $this->createFacility('550e8400-e29b-41d4-a716-446655443036', $otherOrganization, $otherParent, 'Other Child');

    $this->entityManager->flush();
    $this->entityManager->clear();

    $repository = new FacilityRepository($this->entityManager);

    $visibleCounts = $repository->countChildrenByParentIds(
      new FacilityOrganizationId($organization->id),
      [new FacilityId($parentA->id), new FacilityId($parentB->id), new FacilityId($otherParent->id)],
    );

    self::assertSame(1, $visibleCounts[$parentA->id]);
    self::assertSame(1, $visibleCounts[$parentB->id]);
    self::assertArrayNotHasKey($otherParent->id, $visibleCounts);

    $allCounts = $repository->countChildrenByParentIds(
      new FacilityOrganizationId($organization->id),
      [new FacilityId($parentA->id)],
      includeArchived: true,
    );

    self::assertSame(2, $allCounts[$parentA->id]);
  }

  #[Test]
  public function testFindDescendantsWalksTheWholeSubtreeAcrossArchivedNodes(): void
  {
    $organization = $this->createOrganization('550e8400-e29b-41d4-a716-446655443000', 'facility-repository-descendants-a');
    $otherOrganization = $this->createOrganization('550e8400-e29b-41d4-a716-446655443001', 'facility-repository-descendants-b');

    $root = $this->createFacility('550e8400-e29b-41d4-a716-446655443040', $organization, null, 'Root');
    $this->createFacility('550e8400-e29b-41d4-a716-446655443041', $organization, $root, 'Child');
    $child = $this->entityManager->find(FacilityRecord::class, '550e8400-e29b-41d4-a716-446655443041');
    $this->createFacility('550e8400-e29b-41d4-a716-446655443042', $organization, $child, 'Grandchild');
    $archivedBranch = $this->createFacility('550e8400-e29b-41d4-a716-446655443043', $organization, $root, 'Archived Branch', 'archived');
    // A live node beneath an ARCHIVED intermediate node must still be reached.
    $this->createFacility('550e8400-e29b-41d4-a716-446655443044', $organization, $archivedBranch, 'Live Under Archived');
    // A cross-organization subtree must never leak in.
    $otherRoot = $this->createFacility('550e8400-e29b-41d4-a716-446655443045', $otherOrganization, null, 'Other Root');
    $this->createFacility('550e8400-e29b-41d4-a716-446655443046', $otherOrganization, $otherRoot, 'Other Child');

    $this->entityManager->flush();
    $this->entityManager->clear();

    $repository = new FacilityRepository($this->entityManager);
    $organizationId = new FacilityOrganizationId($organization->id);
    $rootId = new FacilityId($root->id);

    // Default: archived nodes are traversed so their live descendants are reached,
    // but the archived nodes themselves are omitted (ordered by name).
    self::assertSame(
      [
        '550e8400-e29b-41d4-a716-446655443041', // Child
        '550e8400-e29b-41d4-a716-446655443042', // Grandchild
        '550e8400-e29b-41d4-a716-446655443044', // Live Under Archived
      ],
      $this->descendantIds($repository->findDescendants($organizationId, $rootId)),
    );

    // includeArchived also returns the archived intermediate node.
    self::assertSame(
      [
        '550e8400-e29b-41d4-a716-446655443043', // Archived Branch
        '550e8400-e29b-41d4-a716-446655443041', // Child
        '550e8400-e29b-41d4-a716-446655443042', // Grandchild
        '550e8400-e29b-41d4-a716-446655443044', // Live Under Archived
      ],
      $this->descendantIds($repository->findDescendants($organizationId, $rootId, includeArchived: true)),
    );
  }

  #[Test]
  public function testHasActiveDescendantsProbesTheSubtreeAndIgnoresDrafts(): void
  {
    $organization = $this->createOrganization('550e8400-e29b-41d4-a716-446655443000', 'facility-repository-active-desc');

    $root = $this->createFacility('550e8400-e29b-41d4-a716-446655443050', $organization, null, 'Root');
    $archivedChild = $this->createFacility('550e8400-e29b-41d4-a716-446655443051', $organization, $root, 'Archived Child', 'archived');
    $this->createFacility('550e8400-e29b-41d4-a716-446655443052', $organization, $archivedChild, 'Live Grandchild');

    $archivedOnlyRoot = $this->createFacility('550e8400-e29b-41d4-a716-446655443053', $organization, null, 'Archived Only Root');
    $this->createFacility('550e8400-e29b-41d4-a716-446655443054', $organization, $archivedOnlyRoot, 'Archived Leaf', 'archived');

    $draftOnlyRoot = $this->createFacility('550e8400-e29b-41d4-a716-446655443055', $organization, null, 'Draft Only Root');
    $draftChild = $this->createFacility('550e8400-e29b-41d4-a716-446655443056', $organization, $draftOnlyRoot, 'Draft Child');
    $draftChild->recordStatus = 'draft';

    $this->entityManager->flush();
    $this->entityManager->clear();

    $repository = new FacilityRepository($this->entityManager);
    $organizationId = new FacilityOrganizationId($organization->id);

    // A live descendant beneath an archived branch is detected.
    self::assertTrue($repository->hasActiveDescendants($organizationId, new FacilityId($root->id)));
    // Only archived descendants: nothing blocks archiving.
    self::assertFalse($repository->hasActiveDescendants($organizationId, new FacilityId($archivedOnlyRoot->id)));
    // A draft (intervention scratchpad) child does not count as a dependent,
    // and is invisible to the descendants listing too.
    self::assertFalse($repository->hasActiveDescendants($organizationId, new FacilityId($draftOnlyRoot->id)));
    self::assertSame([], $repository->findDescendants($organizationId, new FacilityId($draftOnlyRoot->id), includeArchived: true));
  }

  #[Test]
  public function testFindAncestorsWalksTheParentChainRootFirstAndExcludesDraftAncestors(): void
  {
    $organization = $this->createOrganization('550e8400-e29b-41d4-a716-446655443000', 'facility-repository-ancestors-a');

    $root = $this->createFacility('550e8400-e29b-41d4-a716-446655443070', $organization, null, 'Root');
    $middle = $this->createFacility('550e8400-e29b-41d4-a716-446655443071', $organization, $root, 'Middle');
    $leaf = $this->createFacility('550e8400-e29b-41d4-a716-446655443072', $organization, $middle, 'Leaf');

    $draftRoot = $this->createFacility('550e8400-e29b-41d4-a716-446655443073', $organization, null, 'Draft Root');
    $draftRoot->recordStatus = 'draft';
    $draftChild = $this->createFacility('550e8400-e29b-41d4-a716-446655443074', $organization, $draftRoot, 'Draft Child');

    $this->entityManager->flush();
    $this->entityManager->clear();

    $repository = new FacilityRepository($this->entityManager);

    // A root facility has no ancestors.
    self::assertSame([], $repository->findAncestors($root->id));

    // A 3-level chain returns ordered root -> direct parent, excluding the facility itself.
    self::assertSame(
      [
        ['id' => $root->id, 'name' => 'Root', 'type' => 'site'],
        ['id' => $middle->id, 'name' => 'Middle', 'type' => 'building'],
      ],
      $repository->findAncestors($leaf->id),
    );

    // A draft (intervention scratchpad) ancestor is invisible to the walk.
    self::assertSame([], $repository->findAncestors($draftChild->id));
  }

  #[Test]
  public function testFindByOrganizationIdPushesWildcardSafeSearchPredicateIntoSql(): void
  {
    $organization = $this->createOrganization('550e8400-e29b-41d4-a716-446655443000', 'facility-repository-search-a');

    // Contains a literal underscore, which is also a SQL LIKE wildcard
    // (matches any single character) unless properly escaped.
    $literalMatch = $this->createFacility('550e8400-e29b-41d4-a716-446655443060', $organization, null, 'a_b Site');
    // Would ALSO match the unescaped pattern "%a_b%" (the "_" acting as a
    // wildcard), which is exactly the bug this predicate must not reintroduce.
    $this->createFacility('550e8400-e29b-41d4-a716-446655443061', $organization, null, 'axb Site');
    $this->createFacility('550e8400-e29b-41d4-a716-446655443062', $organization, null, 'Completely Unrelated');

    $this->entityManager->flush();
    $this->entityManager->clear();

    $repository = new FacilityRepository($this->entityManager);
    $organizationId = new FacilityOrganizationId($organization->id);

    $results = $repository->findByOrganizationId(organizationId: $organizationId, search: 'a_b');

    self::assertCount(1, $results);
    self::assertSame($literalMatch->id, (string) $results[0]->id());
    self::assertSame(1, $repository->countByOrganizationId(organizationId: $organizationId, search: 'a_b'));

    // Case-insensitive, partial match against the name field.
    self::assertSame(1, $repository->countByOrganizationId(organizationId: $organizationId, search: 'UNRELATED'));
  }

  #[Test]
  public function testSaveThenFindByIdRoundTripsPlanGeometry(): void
  {
    $organization = $this->createOrganization('550e8400-e29b-41d4-a716-446655443000', 'facility-repository-plan-geometry-a');
    $this->entityManager->flush();

    $facilityId = new FacilityId('550e8400-e29b-41d4-a716-446655443070');
    $planGeometry = new PlanGeometry(
      '550e8400-e29b-41d4-a716-446655443999',
      [[0.1, 0.1], [0.4, 0.1], [0.4, 0.4]],
    );
    $facility = Facility::create(
      id: $facilityId,
      organizationId: new FacilityOrganizationId($organization->id),
      type: FacilityType::ZONE,
      name: new FacilityName('Zone With Geometry'),
    );
    $facility->assignPlanGeometry($planGeometry);

    $repository = new FacilityRepository($this->entityManager);
    $repository->save($facility);
    $this->entityManager->clear();

    $found = $repository->findById($facilityId);

    self::assertNotNull($found);
    self::assertNotNull($found->planGeometry());
    self::assertTrue($planGeometry->equals($found->planGeometry()));

    // Clearing round-trips back to NULL, not an empty array.
    $found->clearPlanGeometry();
    $repository->save($found);
    $this->entityManager->clear();

    self::assertNull($repository->findById($facilityId)?->planGeometry());
  }

  #[Test]
  public function testFindZonesForPlanAttachmentReturnsSelfAndDescendantsBoundToTheAttachmentOnly(): void
  {
    $organization = $this->createOrganization('550e8400-e29b-41d4-a716-446655443000', 'facility-repository-plan-overlay-a');
    $otherOrganization = $this->createOrganization('550e8400-e29b-41d4-a716-446655443001', 'facility-repository-plan-overlay-b');
    $this->entityManager->flush();

    $organizationId = new FacilityOrganizationId($organization->id);
    $attachmentId = '550e8400-e29b-41d4-a716-446655443998';
    $otherAttachmentId = '550e8400-e29b-41d4-a716-446655443997';

    $root = $this->planZone('550e8400-e29b-41d4-a716-446655443080', $organizationId, null, 'Root Zone', $attachmentId, [[0.0, 0.0], [1.0, 0.0], [1.0, 1.0]]);
    $child = $this->planZone('550e8400-e29b-41d4-a716-446655443081', $organizationId, $root->id(), 'Child Zone', $attachmentId, [[0.1, 0.1], [0.4, 0.1], [0.4, 0.4]]);
    // Sibling of the root, bound to a DIFFERENT attachment — proves the
    // JSONB filter, not just the subtree walk, excludes it.
    $this->planZone('550e8400-e29b-41d4-a716-446655443082', $organizationId, null, 'Unrelated Sibling', $otherAttachmentId, [[0.2, 0.2], [0.5, 0.2], [0.5, 0.5]]);
    // A facility in another organization, coincidentally bound to the same
    // attachment id — must never leak across the organization boundary.
    $this->planZone('550e8400-e29b-41d4-a716-446655443083', new FacilityOrganizationId($otherOrganization->id), null, 'Cross-Org Zone', $attachmentId, [[0.3, 0.3], [0.6, 0.3], [0.6, 0.6]]);

    $repository = new FacilityRepository($this->entityManager);
    $repository->save($root);
    $repository->save($child);
    $this->entityManager->clear();

    $zones = $repository->findZonesForPlanAttachment($organizationId, $root->id(), $attachmentId);

    self::assertCount(2, $zones);
    $ids = array_column($zones, 'facilityId');
    self::assertContains((string) $root->id(), $ids);
    self::assertContains((string) $child->id(), $ids);
  }

  /**
   * Method planZone.
   *
   * Builds (but does not persist through the repository) a zone facility
   * carrying a plan geometry bound to the given attachment identifier.
   *
   * @since 1.0.0
   *
   * @param list<array{0: float, 1: float}> $points
   */
  private function planZone(
    string $id,
    FacilityOrganizationId $organizationId,
    ?FacilityId $parentFacilityId,
    string $name,
    string $attachmentId,
    array $points,
  ): Facility {
    $facility = Facility::create(
      id: new FacilityId($id),
      organizationId: $organizationId,
      type: FacilityType::ZONE,
      name: new FacilityName($name),
      parentFacilityId: $parentFacilityId,
    );
    $facility->assignPlanGeometry(new PlanGeometry($attachmentId, $points));

    return $facility;
  }

  /**
   * @param list<Facility> $facilities
   *
   * @return list<string>
   */
  private function descendantIds(array $facilities): array
  {
    $ids = [];
    foreach ($facilities as $facility) {
      $ids[] = (string) $facility->id();
    }

    return $ids;
  }

  private function createOrganization(string $id, string $slug): OrganizationRecord
  {
    $organization = new OrganizationRecord();
    $organization->id = $id;
    $organization->name = 'Facility Repository Test';
    $organization->slug = $slug;
    $organization->ownerUserId = '550e8400-e29b-41d4-a716-446655443900';
    $organization->createdByUserId = '550e8400-e29b-41d4-a716-446655443900';
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-02-12T10:00:00+00:00');
    $organization->updatedAt = $organization->createdAt;
    $this->entityManager->persist($organization);

    return $organization;
  }

  private function createFacility(
    string $id,
    OrganizationRecord $organization,
    ?FacilityRecord $parentFacility,
    string $name,
    string $status = 'active',
    ?float $latitude = null,
    ?float $longitude = null,
  ): FacilityRecord {
    $facility = new FacilityRecord();
    $facility->id = $id;
    $facility->organization = $organization;
    $facility->parentFacility = $parentFacility;
    $facility->type = null === $parentFacility ? 'site' : 'building';
    $facility->name = $name;
    $facility->code = null;
    $facility->status = $status;
    $facility->address = null;
    $facility->latitude = $latitude;
    $facility->longitude = $longitude;
    $facility->metadata = [];
    $facility->createdAt = new DateTimeImmutable('2026-02-12T10:00:00+00:00');
    $facility->updatedAt = $facility->createdAt;
    $this->entityManager->persist($facility);

    return $facility;
  }

  private function removeOrganization(string $id): void
  {
    $organization = $this->entityManager->find(OrganizationRecord::class, $id);
    if ($organization instanceof OrganizationRecord) {
      $this->entityManager->remove($organization);
      $this->entityManager->flush();
    }
  }
}
