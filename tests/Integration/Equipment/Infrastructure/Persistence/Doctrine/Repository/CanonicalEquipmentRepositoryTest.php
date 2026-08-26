<?php

declare(strict_types=1);

namespace Tests\Integration\Equipment\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Domain\ValueObject\{CanonicalEquipmentPatch, EquipmentId, EquipmentRecordStatus, EquipmentStatus};
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Equipment\Infrastructure\Persistence\Doctrine\Repository\CanonicalEquipmentRepository;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test CanonicalEquipmentRepositoryTest.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CanonicalEquipmentRepository::class)]
final class CanonicalEquipmentRepositoryTest extends KernelTestCase
{
  // #region Constants
  private const string ORGANIZATION_ID = '660e8400-e29b-41d4-a716-446655464001';

  private const string EQUIPMENT_ID = '660e8400-e29b-41d4-a716-446655464002';

  private const string INTERVENTION_ID = '660e8400-e29b-41d4-a716-446655464003';

  private const string CLIENT_ID = '660e8400-e29b-41d4-a716-446655464004';

  private const string ABSENT_ID = '660e8400-e29b-41d4-a716-4466554640ff';
  // #endregion

  // #region Properties
  private EntityManagerInterface $entityManager;

  private CanonicalEquipmentRepository $repository;
  // #endregion

  // #region Fixture
  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();

    $this->repository = new CanonicalEquipmentRepository($this->entityManager);

    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $organization->name = 'Canonical Equipment Repository Test';
    $organization->slug = 'canonical-equipment-repository-test';
    $organization->ownerUserId = '660e8400-e29b-41d4-a716-446655469003';
    $organization->createdByUserId = '660e8400-e29b-41d4-a716-446655469003';
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = $now;
    $organization->updatedAt = $now;
    $this->entityManager->persist($organization);

    $equipment = new EquipmentRecord();
    $equipment->id = self::EQUIPMENT_ID;
    $equipment->organization = $organization;
    $equipment->recordStatus = 'draft';
    $equipment->interventionId = self::INTERVENTION_ID;
    $equipment->clientId = self::CLIENT_ID;
    $equipment->revision = 4;
    $equipment->type = 'fire_extinguisher';
    $equipment->brand = 'Seeded brand';
    $equipment->model = 'Seeded model';
    $equipment->status = 'in_stock';
    $equipment->createdAt = $now;
    $equipment->updatedAt = $now;
    $this->entityManager->persist($equipment);

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
    $equipment = $this->repository->findById(EquipmentId::fromString(self::EQUIPMENT_ID));

    self::assertNotNull($equipment);
    self::assertSame(EquipmentRecordStatus::DRAFT, $equipment->recordStatus());
    self::assertSame(self::INTERVENTION_ID, $equipment->interventionId());
    self::assertSame(4, $equipment->revision());
    self::assertSame(EquipmentStatus::IN_STOCK, $equipment->status());
    self::assertSame('Seeded brand', $equipment->brand());
    self::assertSame(self::ORGANIZATION_ID, (string) $equipment->organizationId());
  }

  /**
   * Method testSaveWritesTheMutableColumnsAndLeavesTheOwnershipOnesAlone.
   *
   * `record_status`, `intervention_id` and `client_id` are the columns the
   * canonical surface must never move — a PATCH on a scratchpad row that
   * silently published it, or detached it from its intervention, would be
   * invisible in the response and permanent in the table.
   *
   * @return void no return value
   */
  #[Test]
  public function testSaveWritesTheMutableColumnsAndLeavesTheOwnershipOnesAlone(): void
  {
    $equipment = $this->repository->findById(EquipmentId::fromString(self::EQUIPMENT_ID));
    self::assertNotNull($equipment);

    $equipment->applyPatch(new CanonicalEquipmentPatch(
      hasType: true,
      type: 'sprinkler',
      hasBrand: true,
      brand: null,
      hasLocationLabel: true,
      locationLabel: 'Level 3',
    ));
    $this->repository->save($equipment);
    $this->entityManager->clear();

    $reloaded = $this->repository->findById(EquipmentId::fromString(self::EQUIPMENT_ID));

    self::assertNotNull($reloaded);
    self::assertSame('sprinkler', $reloaded->type());
    self::assertNull($reloaded->brand());
    self::assertSame('Level 3', $reloaded->locationLabel());
    self::assertSame(5, $reloaded->revision());
    self::assertSame(EquipmentRecordStatus::DRAFT, $reloaded->recordStatus());
    self::assertSame(self::INTERVENTION_ID, $reloaded->interventionId());

    $record = $this->entityManager->find(EquipmentRecord::class, self::EQUIPMENT_ID);
    self::assertInstanceOf(EquipmentRecord::class, $record);
    self::assertSame(self::CLIENT_ID, $record->clientId);
  }

  /**
   * Method testSaveOnAnAbsentRowIsANoOp.
   *
   * The port never inserts: canonical rows are born through the `Equipment`
   * aggregate.
   *
   * @return void no return value
   */
  #[Test]
  public function testSaveOnAnAbsentRowIsANoOp(): void
  {
    $equipment = $this->repository->findById(EquipmentId::fromString(self::EQUIPMENT_ID));
    self::assertNotNull($equipment);

    $this->repository->delete(EquipmentId::fromString(self::EQUIPMENT_ID));
    $this->repository->save($equipment);
    $this->entityManager->clear();

    self::assertNull($this->repository->findById(EquipmentId::fromString(self::EQUIPMENT_ID)));
  }

  /**
   * Method testDeleteRemovesTheRowAndIsIdempotent.
   *
   * @return void no return value
   */
  #[Test]
  public function testDeleteRemovesTheRowAndIsIdempotent(): void
  {
    $id = EquipmentId::fromString(self::EQUIPMENT_ID);

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
    self::assertNull($this->repository->findById(EquipmentId::fromString(self::ABSENT_ID)));
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
      'DELETE FROM equipment WHERE organization_id = :organizationId',
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
