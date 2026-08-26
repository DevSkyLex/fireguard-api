<?php

declare(strict_types=1);

namespace Tests\Integration\Facility\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Facility\Domain\ValueObject\{CanonicalFacilityPatch, FacilityId, FacilityRecordStatus, FacilityStatus, FacilityType};
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Facility\Infrastructure\Persistence\Doctrine\Repository\CanonicalFacilityRepository;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test CanonicalFacilityRepositoryTest.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CanonicalFacilityRepository::class)]
final class CanonicalFacilityRepositoryTest extends KernelTestCase
{
  // #region Constants
  private const string ORGANIZATION_ID = '660e8400-e29b-41d4-a716-446655465001';

  private const string ROOT_ID = '660e8400-e29b-41d4-a716-446655465002';

  private const string CHILD_ID = '660e8400-e29b-41d4-a716-446655465003';

  private const string GRANDCHILD_ID = '660e8400-e29b-41d4-a716-446655465004';

  private const string INTERVENTION_ID = '660e8400-e29b-41d4-a716-446655465005';

  private const string CLIENT_ID = '660e8400-e29b-41d4-a716-446655465006';

  private const string ABSENT_ID = '660e8400-e29b-41d4-a716-4466554650ff';
  // #endregion

  // #region Properties
  private EntityManagerInterface $entityManager;

  private CanonicalFacilityRepository $repository;
  // #endregion

  // #region Fixture
  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();

    $this->repository = new CanonicalFacilityRepository($this->entityManager);

    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $organization->name = 'Canonical Facility Repository Test';
    $organization->slug = 'canonical-facility-repository-test';
    $organization->ownerUserId = '660e8400-e29b-41d4-a716-446655469004';
    $organization->createdByUserId = '660e8400-e29b-41d4-a716-446655469004';
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;
    $this->entityManager->persist($organization);

    $root = $this->newFacility(self::ROOT_ID, $organization, null, $now);
    $this->entityManager->persist($root);
    $this->entityManager->flush();

    $child = $this->newFacility(self::CHILD_ID, $organization, $root, $now);
    $child->recordStatus = 'draft';
    $child->interventionId = self::INTERVENTION_ID;
    $child->clientId = self::CLIENT_ID;
    $child->revision = 4;
    $child->code = 'B-1';
    $this->entityManager->persist($child);
    $this->entityManager->flush();

    $this->entityManager->persist($this->newFacility(self::GRANDCHILD_ID, $organization, $child, $now));
    $this->entityManager->flush();
    $this->entityManager->clear();
  }

  protected function tearDown(): void
  {
    $this->cleanup();
    parent::tearDown();
    $this->entityManager->close();
  }
  // #endregion

  // #region Tests
  /**
   * Method testFindByIdCarriesTheColumnsTheAggregateDoesNot.
   *
   * @return void no return value
   */
  #[Test]
  public function testFindByIdCarriesTheColumnsTheAggregateDoesNot(): void
  {
    $facility = $this->repository->findById(FacilityId::fromString(self::CHILD_ID));

    self::assertNotNull($facility);
    self::assertSame(FacilityRecordStatus::DRAFT, $facility->recordStatus());
    self::assertSame(self::INTERVENTION_ID, $facility->interventionId());
    self::assertSame(4, $facility->revision());
    self::assertSame(self::ROOT_ID, $facility->parentFacilityId());
    self::assertSame(FacilityStatus::ACTIVE, $facility->status());
    self::assertSame(FacilityType::BUILDING, $facility->type());
    self::assertSame('B-1', $facility->code());
  }

  /**
   * Method testSaveWritesTheMutableColumnsAndLeavesTheOwnershipOnesAlone.
   *
   * @return void no return value
   */
  #[Test]
  public function testSaveWritesTheMutableColumnsAndLeavesTheOwnershipOnesAlone(): void
  {
    $facility = $this->repository->findById(FacilityId::fromString(self::CHILD_ID));
    self::assertNotNull($facility);

    $facility->applyPatch(new CanonicalFacilityPatch(
      hasName: true,
      name: 'Rewritten',
      hasCode: true,
      code: null,
      hasStatus: true,
      status: 'archived',
    ));
    $this->repository->save($facility);
    $this->entityManager->clear();

    $reloaded = $this->repository->findById(FacilityId::fromString(self::CHILD_ID));

    self::assertNotNull($reloaded);
    self::assertSame('Rewritten', $reloaded->name());
    self::assertNull($reloaded->code());
    self::assertSame(FacilityStatus::ARCHIVED, $reloaded->status());
    self::assertSame(5, $reloaded->revision());
    self::assertSame(FacilityRecordStatus::DRAFT, $reloaded->recordStatus());
    self::assertSame(self::INTERVENTION_ID, $reloaded->interventionId());

    $record = $this->entityManager->find(FacilityRecord::class, self::CHILD_ID);
    self::assertInstanceOf(FacilityRecord::class, $record);
    self::assertSame(self::CLIENT_ID, $record->clientId);
  }

  /**
   * Method testSaveMovesTheParentAssociation.
   *
   * The association is resolved in the repository, not the mapper: turning an
   * identifier into a reference needs an entity manager.
   *
   * @return void no return value
   */
  #[Test]
  public function testSaveMovesTheParentAssociation(): void
  {
    $facility = $this->repository->findById(FacilityId::fromString(self::GRANDCHILD_ID));
    self::assertNotNull($facility);
    self::assertSame(self::CHILD_ID, $facility->parentFacilityId());

    $facility->applyPatch(new CanonicalFacilityPatch(hasParent: true, parentFacilityId: null));
    $this->repository->save($facility);
    $this->entityManager->clear();

    $detached = $this->repository->findById(FacilityId::fromString(self::GRANDCHILD_ID));
    self::assertNotNull($detached);
    self::assertNull($detached->parentFacilityId());
  }

  /**
   * Method testCountChildrenSeesOnlyDirectChildren.
   *
   * @return void no return value
   */
  #[Test]
  public function testCountChildrenSeesOnlyDirectChildren(): void
  {
    self::assertSame(1, $this->repository->countChildren(FacilityId::fromString(self::ROOT_ID)));
    self::assertSame(1, $this->repository->countChildren(FacilityId::fromString(self::CHILD_ID)));
    self::assertSame(0, $this->repository->countChildren(FacilityId::fromString(self::GRANDCHILD_ID)));
    self::assertSame(0, $this->repository->countChildren(FacilityId::fromString(self::ABSENT_ID)));
  }

  /**
   * Method testAncestorIdsAreWalkedNearestFirst.
   *
   * @return void no return value
   */
  #[Test]
  public function testAncestorIdsAreWalkedNearestFirst(): void
  {
    self::assertSame(
      [self::CHILD_ID, self::ROOT_ID],
      $this->repository->ancestorIdsOf(FacilityId::fromString(self::GRANDCHILD_ID)),
    );
    self::assertSame([], $this->repository->ancestorIdsOf(FacilityId::fromString(self::ROOT_ID)));
    self::assertSame([], $this->repository->ancestorIdsOf(FacilityId::fromString(self::ABSENT_ID)));
  }

  /**
   * Method testDeleteRemovesTheRowAndIsIdempotent.
   *
   * @return void no return value
   */
  #[Test]
  public function testDeleteRemovesTheRowAndIsIdempotent(): void
  {
    $id = FacilityId::fromString(self::GRANDCHILD_ID);

    $this->repository->delete($id);
    self::assertNull($this->repository->findById($id));

    $this->repository->delete($id);
    self::assertNull($this->repository->findById($id));
  }

  /**
   * Method testFindByIdAnswersNullForAnAbsentRow.
   *
   * @return void no return value
   */
  #[Test]
  public function testFindByIdAnswersNullForAnAbsentRow(): void
  {
    self::assertNull($this->repository->findById(FacilityId::fromString(self::ABSENT_ID)));
  }
  // #endregion

  // #region Helpers
  /**
   * Method newFacility.
   *
   * @param string $id the facility identifier
   * @param OrganizationRecord $organization the owning organization
   * @param ?FacilityRecord $parent the parent facility
   * @param DateTimeImmutable $now the fixed clock
   *
   * @return FacilityRecord the facility record
   */
  private function newFacility(
    string $id,
    OrganizationRecord $organization,
    ?FacilityRecord $parent,
    DateTimeImmutable $now,
  ): FacilityRecord {
    $facility = new FacilityRecord();
    $facility->id = $id;
    $facility->organization = $organization;
    $facility->parentFacility = $parent;
    $facility->recordStatus = 'published';
    $facility->revision = 1;
    $facility->type = null === $parent ? 'site' : 'building';
    $facility->name = 'Canonical Facility Repository Test ' . $id;
    $facility->status = 'active';
    $facility->metadata = [];
    $facility->createdAt = $now;
    $facility->updatedAt = $now;

    return $facility;
  }

  /**
   * Method cleanup.
   */
  private function cleanup(): void
  {
    $connection = $this->entityManager->getConnection();
    // Deepest first: the foreign key is ON DELETE SET NULL, not CASCADE.
    foreach ([self::GRANDCHILD_ID, self::CHILD_ID, self::ROOT_ID] as $facilityId) {
      $connection->executeStatement('DELETE FROM facilities WHERE id = :id', ['id' => $facilityId]);
    }
    $connection->executeStatement(
      'DELETE FROM organizations WHERE id = :organizationId',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $this->entityManager->clear();
  }
  // #endregion
}
