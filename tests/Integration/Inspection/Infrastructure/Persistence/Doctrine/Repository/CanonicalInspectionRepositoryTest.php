<?php

declare(strict_types=1);

namespace Tests\Integration\Inspection\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Inspection\Domain\ValueObject\{CanonicalInspectionPatch, InspectionId, InspectionRecordStatus, InspectionResult, InspectionStatus};
use Inspection\Infrastructure\Persistence\Doctrine\Record\InspectionRecord;
use Inspection\Infrastructure\Persistence\Doctrine\Repository\CanonicalInspectionRepository;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test CanonicalInspectionRepositoryTest.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CanonicalInspectionRepository::class)]
final class CanonicalInspectionRepositoryTest extends KernelTestCase
{
  // #region Constants
  private const string ORGANIZATION_ID = '660e8400-e29b-41d4-a716-446655463001';

  private const string INSPECTION_ID = '660e8400-e29b-41d4-a716-446655463002';

  private const string INTERVENTION_ID = '660e8400-e29b-41d4-a716-446655463003';

  private const string ABSENT_ID = '660e8400-e29b-41d4-a716-4466554630ff';
  // #endregion

  // #region Properties
  private EntityManagerInterface $entityManager;

  private CanonicalInspectionRepository $repository;
  // #endregion

  // #region Fixture
  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();

    $this->repository = new CanonicalInspectionRepository($this->entityManager);

    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $organization->name = 'Canonical Inspection Repository Test';
    $organization->slug = 'canonical-inspection-repository-test';
    $organization->ownerUserId = '660e8400-e29b-41d4-a716-446655469002';
    $organization->createdByUserId = '660e8400-e29b-41d4-a716-446655469002';
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;
    $this->entityManager->persist($organization);

    $inspection = new InspectionRecord();
    $inspection->id = self::INSPECTION_ID;
    $inspection->organization = $organization;
    $inspection->recordStatus = 'draft';
    $inspection->interventionId = self::INTERVENTION_ID;
    $inspection->revision = 4;
    $inspection->equipmentId = '660e8400-e29b-41d4-a716-446655468890';
    $inspection->inspectorType = 'user';
    $inspection->inspectorName = 'Jane Doe';
    $inspection->result = 'pass';
    $inspection->status = 'submitted';
    $inspection->notes = 'Seeded notes';
    $inspection->signature = 'Seeded signature';
    $inspection->performedAt = $now;
    $inspection->createdAt = $now;
    $inspection->updatedAt = $now;
    $this->entityManager->persist($inspection);

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
   * Method testFindByIdCarriesTheThreeColumnsTheAggregateDoesNot.
   *
   * @return void no return value
   */
  #[Test]
  public function testFindByIdCarriesTheThreeColumnsTheAggregateDoesNot(): void
  {
    $inspection = $this->repository->findById(InspectionId::fromString(self::INSPECTION_ID));

    self::assertNotNull($inspection);
    self::assertSame(InspectionRecordStatus::DRAFT, $inspection->recordStatus());
    self::assertSame(self::INTERVENTION_ID, $inspection->interventionId());
    self::assertSame(4, $inspection->revision());
    self::assertSame(InspectionStatus::SUBMITTED, $inspection->status());
    self::assertSame(InspectionResult::PASS, $inspection->result());
    self::assertSame('Seeded notes', $inspection->notes());
    self::assertSame(self::ORGANIZATION_ID, (string) $inspection->organizationId());
  }

  /**
   * Method testSaveWritesTheMutableColumnsAndLeavesTheOwnershipOnesAlone.
   *
   * `record_status` and `intervention_id` are the columns the canonical
   * surface must never move — a PATCH on a scratchpad row that silently
   * published it, or detached it from its intervention, would be invisible in
   * the response and permanent in the table.
   *
   * @return void no return value
   */
  #[Test]
  public function testSaveWritesTheMutableColumnsAndLeavesTheOwnershipOnesAlone(): void
  {
    $inspection = $this->repository->findById(InspectionId::fromString(self::INSPECTION_ID));
    self::assertNotNull($inspection);

    $inspection->applyPatch(new CanonicalInspectionPatch(
      hasResult: true,
      result: 'fail',
      hasNotes: true,
      notes: 'Rewritten',
      hasSignature: true,
      signature: null,
    ));
    $this->repository->save($inspection);
    $this->entityManager->clear();

    $reloaded = $this->repository->findById(InspectionId::fromString(self::INSPECTION_ID));

    self::assertNotNull($reloaded);
    self::assertSame(InspectionResult::FAIL, $reloaded->result());
    self::assertSame('Rewritten', $reloaded->notes());
    self::assertNull($reloaded->signature());
    self::assertSame(5, $reloaded->revision());
    self::assertSame(InspectionRecordStatus::DRAFT, $reloaded->recordStatus());
    self::assertSame(self::INTERVENTION_ID, $reloaded->interventionId());
  }

  /**
   * Method testSaveOnAnAbsentRowIsANoOp.
   *
   * The port never inserts: canonical rows are born through the `Inspection`
   * aggregate.
   *
   * @return void no return value
   */
  #[Test]
  public function testSaveOnAnAbsentRowIsANoOp(): void
  {
    $inspection = $this->repository->findById(InspectionId::fromString(self::INSPECTION_ID));
    self::assertNotNull($inspection);

    $this->repository->delete(InspectionId::fromString(self::INSPECTION_ID));
    $this->repository->save($inspection);
    $this->entityManager->clear();

    self::assertNull($this->repository->findById(InspectionId::fromString(self::INSPECTION_ID)));
  }

  /**
   * Method testDeleteRemovesTheRowAndIsIdempotent.
   *
   * @return void no return value
   */
  #[Test]
  public function testDeleteRemovesTheRowAndIsIdempotent(): void
  {
    $id = InspectionId::fromString(self::INSPECTION_ID);

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
    self::assertNull($this->repository->findById(InspectionId::fromString(self::ABSENT_ID)));
  }
  // #endregion

  // #region Helpers
  /**
   * Method cleanup.
   */
  private function cleanup(): void
  {
    $connection = $this->entityManager->getConnection();
    $connection->executeStatement(
      'DELETE FROM inspections WHERE organization_id = :organizationId',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $connection->executeStatement(
      'DELETE FROM organizations WHERE id = :organizationId',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $this->entityManager->clear();
  }
  // #endregion
}
