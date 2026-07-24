<?php

declare(strict_types=1);

namespace Tests\Integration\Inspection\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Inspection\Domain\Model\Inspection\Inspection;
use Inspection\Domain\ValueObject\{
  InspectionEquipmentId,
  InspectionId,
  InspectionOrganizationId,
  InspectionResult,
  Inspector
};
use Inspection\Infrastructure\Persistence\Doctrine\Repository\InspectionRepository;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test InspectionRepositoryTest.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InspectionRepository::class)]
final class InspectionRepositoryTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = '880e8400-e29b-41d4-a716-446655481001';

  private const string OTHER_ORGANIZATION_ID = '880e8400-e29b-41d4-a716-446655481002';

  private const string INSPECTION_ID = '880e8400-e29b-41d4-a716-446655481101';

  private const string SECOND_INSPECTION_ID = '880e8400-e29b-41d4-a716-446655481102';

  private const string FOREIGN_INSPECTION_ID = '880e8400-e29b-41d4-a716-446655481103';

  private const string EQUIPMENT_ID = '880e8400-e29b-41d4-a716-446655488001';

  private const string SECOND_EQUIPMENT_ID = '880e8400-e29b-41d4-a716-446655488002';

  private const string INSPECTOR_USER_ID = '880e8400-e29b-41d4-a716-446655489000';

  private EntityManagerInterface $entityManager;

  private InspectionRepository $repository;

  protected function setUp(): void
  {
    self::bootKernel();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();

    $this->repository = new InspectionRepository($this->entityManager);

    $this->entityManager->persist($this->createOrganization(self::ORGANIZATION_ID, 'Inspection Repository Org', 'inspection-repository-org'));
    $this->entityManager->persist($this->createOrganization(self::OTHER_ORGANIZATION_ID, 'Inspection Repository Org B', 'inspection-repository-org-b'));
    $this->entityManager->flush();
  }

  protected function tearDown(): void
  {
    $this->cleanup();
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testSaveThenFindByIdRoundTripsAnInspection(): void
  {
    $this->repository->save($this->createInspection(
      id: self::INSPECTION_ID,
      organizationId: self::ORGANIZATION_ID,
      equipmentId: self::EQUIPMENT_ID,
    ));

    $found = $this->repository->findById(InspectionId::fromString(self::INSPECTION_ID));

    self::assertNotNull($found);
    self::assertSame(self::INSPECTION_ID, (string) $found->id());
    self::assertSame(self::EQUIPMENT_ID, (string) $found->equipmentId());
    self::assertSame('pass', $found->result()->value);
    self::assertSame('draft', $found->status()->value);
    self::assertSame(self::INSPECTOR_USER_ID, $found->inspector()->userId);
  }

  #[Test]
  public function testFindByIdReturnsNullForUnknownId(): void
  {
    self::assertNull($this->repository->findById(InspectionId::fromString(self::FOREIGN_INSPECTION_ID)));
  }

  #[Test]
  public function testFindByOrganizationIdIsScopedToTheOrganization(): void
  {
    $this->repository->save($this->createInspection(
      id: self::INSPECTION_ID,
      organizationId: self::ORGANIZATION_ID,
      equipmentId: self::EQUIPMENT_ID,
    ));
    $this->repository->save($this->createInspection(
      id: self::SECOND_INSPECTION_ID,
      organizationId: self::ORGANIZATION_ID,
      equipmentId: self::SECOND_EQUIPMENT_ID,
    ));
    $this->repository->save($this->createInspection(
      id: self::FOREIGN_INSPECTION_ID,
      organizationId: self::OTHER_ORGANIZATION_ID,
      equipmentId: self::EQUIPMENT_ID,
    ));

    $inOrganization = $this->repository->findByOrganizationId(
      InspectionOrganizationId::fromString(self::ORGANIZATION_ID),
    );

    self::assertCount(2, $inOrganization);
    self::assertSame(2, $this->repository->countByOrganizationId(
      InspectionOrganizationId::fromString(self::ORGANIZATION_ID),
    ));
    self::assertSame(1, $this->repository->countByOrganizationId(
      InspectionOrganizationId::fromString(self::OTHER_ORGANIZATION_ID),
    ));
  }

  #[Test]
  public function testFindEquipmentIdsByIdsResolvesEachRequestedInspection(): void
  {
    $this->repository->save($this->createInspection(
      id: self::INSPECTION_ID,
      organizationId: self::ORGANIZATION_ID,
      equipmentId: self::EQUIPMENT_ID,
    ));
    $this->repository->save($this->createInspection(
      id: self::SECOND_INSPECTION_ID,
      organizationId: self::ORGANIZATION_ID,
      equipmentId: self::SECOND_EQUIPMENT_ID,
    ));

    $equipmentIds = $this->repository->findEquipmentIdsByIds([
      self::INSPECTION_ID,
      self::SECOND_INSPECTION_ID,
    ]);

    self::assertSame(
      [
        self::INSPECTION_ID => self::EQUIPMENT_ID,
        self::SECOND_INSPECTION_ID => self::SECOND_EQUIPMENT_ID,
      ],
      $equipmentIds,
    );
  }

  #[Test]
  public function testFindEquipmentIdsByIdsReturnsEmptyArrayWhenNoIdsRequested(): void
  {
    self::assertSame([], $this->repository->findEquipmentIdsByIds([]));
  }

  #[Test]
  public function testCountOverviewByOrganizationIdAggregatesStatusesAndResults(): void
  {
    $this->repository->save($this->createInspection(
      id: self::INSPECTION_ID,
      organizationId: self::ORGANIZATION_ID,
      equipmentId: self::EQUIPMENT_ID,
    ));
    $this->repository->save($this->createInspection(
      id: self::SECOND_INSPECTION_ID,
      organizationId: self::ORGANIZATION_ID,
      equipmentId: self::SECOND_EQUIPMENT_ID,
    ));

    $overview = $this->repository->countOverviewByOrganizationId(
      InspectionOrganizationId::fromString(self::ORGANIZATION_ID),
    );

    self::assertSame(2, $overview['total']);
    self::assertSame(2, $overview['draft']);
    self::assertSame(2, $overview['pass']);
    self::assertSame(0, $overview['fail']);
  }

  private function createInspection(
    string $id,
    string $organizationId,
    string $equipmentId,
  ): Inspection {
    return Inspection::create(
      id: InspectionId::fromString($id),
      organizationId: InspectionOrganizationId::fromString($organizationId),
      equipmentId: InspectionEquipmentId::fromString($equipmentId),
      inspector: Inspector::forUser(self::INSPECTOR_USER_ID, 'Jane Doe'),
      result: InspectionResult::PASS,
      performedAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    );
  }

  private function createOrganization(string $id, string $name, string $slug): OrganizationRecord
  {
    $organization = new OrganizationRecord();
    $organization->id = $id;
    $organization->name = $name;
    $organization->slug = $slug;
    $organization->ownerUserId = self::INSPECTOR_USER_ID;
    $organization->createdByUserId = self::INSPECTOR_USER_ID;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $organization->updatedAt = $organization->createdAt;

    return $organization;
  }

  private function cleanup(): void
  {
    $connection = $this->entityManager->getConnection();
    $inspectionIds = [self::INSPECTION_ID, self::SECOND_INSPECTION_ID, self::FOREIGN_INSPECTION_ID];
    $connection->executeStatement(
      'DELETE FROM non_conformities WHERE inspection_id IN (:inspectionIds)',
      ['inspectionIds' => $inspectionIds],
      ['inspectionIds' => ArrayParameterType::STRING],
    );
    $connection->executeStatement(
      'DELETE FROM inspections WHERE id IN (:inspectionIds)',
      ['inspectionIds' => $inspectionIds],
      ['inspectionIds' => ArrayParameterType::STRING],
    );
    $connection->executeStatement(
      'DELETE FROM organizations WHERE id IN (:organizationIds)',
      ['organizationIds' => [self::ORGANIZATION_ID, self::OTHER_ORGANIZATION_ID]],
      ['organizationIds' => ArrayParameterType::STRING],
    );
    $this->entityManager->clear();
  }
}
