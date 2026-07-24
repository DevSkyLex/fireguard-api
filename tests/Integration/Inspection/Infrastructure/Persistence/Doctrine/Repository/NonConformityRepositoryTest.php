<?php

declare(strict_types=1);

namespace Tests\Integration\Inspection\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Inspection\Domain\Model\NonConformity\NonConformity;
use Inspection\Domain\ValueObject\{
  InspectionOrganizationId,
  NonConformityId,
  NonConformityInspectionId,
  NonConformitySeverity
};
use Inspection\Infrastructure\Persistence\Doctrine\Record\InspectionRecord;
use Inspection\Infrastructure\Persistence\Doctrine\Repository\NonConformityRepository;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test NonConformityRepositoryTest.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(NonConformityRepository::class)]
final class NonConformityRepositoryTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = '990e8400-e29b-41d4-a716-446655491001';

  private const string OTHER_ORGANIZATION_ID = '990e8400-e29b-41d4-a716-446655491002';

  private const string INSPECTION_ID = '990e8400-e29b-41d4-a716-446655491101';

  private const string FOREIGN_INSPECTION_ID = '990e8400-e29b-41d4-a716-446655491102';

  private const string NON_CONFORMITY_ID = '990e8400-e29b-41d4-a716-446655491201';

  private const string SECOND_NON_CONFORMITY_ID = '990e8400-e29b-41d4-a716-446655491202';

  private const string FOREIGN_NON_CONFORMITY_ID = '990e8400-e29b-41d4-a716-446655491203';

  private const string UNKNOWN_NON_CONFORMITY_ID = '990e8400-e29b-41d4-a716-446655491299';

  private const string OWNER_USER_ID = '990e8400-e29b-41d4-a716-446655499000';

  private EntityManagerInterface $entityManager;

  private NonConformityRepository $repository;

  protected function setUp(): void
  {
    self::bootKernel();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();

    $this->repository = new NonConformityRepository($this->entityManager);

    $organization = $this->createOrganization(self::ORGANIZATION_ID, 'Non-Conformity Repository Org', 'non-conformity-repository-org');
    $otherOrganization = $this->createOrganization(self::OTHER_ORGANIZATION_ID, 'Non-Conformity Repository Org B', 'non-conformity-repository-org-b');
    $this->entityManager->persist($organization);
    $this->entityManager->persist($otherOrganization);

    $this->entityManager->persist($this->createInspection(self::INSPECTION_ID, $organization));
    $this->entityManager->persist($this->createInspection(self::FOREIGN_INSPECTION_ID, $otherOrganization));

    $this->entityManager->flush();
  }

  protected function tearDown(): void
  {
    $this->cleanup();
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testSaveThenFindByIdRoundTripsANonConformity(): void
  {
    $this->repository->save(NonConformity::create(
      id: NonConformityId::fromString(self::NON_CONFORMITY_ID),
      inspectionId: NonConformityInspectionId::fromString(self::INSPECTION_ID),
      description: 'Fire extinguisher pressure too low',
      severity: NonConformitySeverity::HIGH,
    ));

    $found = $this->repository->findById(NonConformityId::fromString(self::NON_CONFORMITY_ID));

    self::assertNotNull($found);
    self::assertSame(self::NON_CONFORMITY_ID, (string) $found->id());
    self::assertSame(self::INSPECTION_ID, (string) $found->inspectionId());
    self::assertSame('Fire extinguisher pressure too low', $found->description());
    self::assertSame('high', $found->severity()->value);
    self::assertSame('open', $found->status()->value);
  }

  #[Test]
  public function testFindByIdReturnsNullForUnknownId(): void
  {
    self::assertNull($this->repository->findById(NonConformityId::fromString(self::UNKNOWN_NON_CONFORMITY_ID)));
  }

  #[Test]
  public function testFindByInspectionIdIsScopedToTheInspection(): void
  {
    $this->repository->save(NonConformity::create(
      id: NonConformityId::fromString(self::NON_CONFORMITY_ID),
      inspectionId: NonConformityInspectionId::fromString(self::INSPECTION_ID),
      description: 'Missing signage',
      severity: NonConformitySeverity::MEDIUM,
    ));
    $this->repository->save(NonConformity::create(
      id: NonConformityId::fromString(self::SECOND_NON_CONFORMITY_ID),
      inspectionId: NonConformityInspectionId::fromString(self::INSPECTION_ID),
      description: 'Blocked exit',
      severity: NonConformitySeverity::CRITICAL,
    ));
    $this->repository->save(NonConformity::create(
      id: NonConformityId::fromString(self::FOREIGN_NON_CONFORMITY_ID),
      inspectionId: NonConformityInspectionId::fromString(self::FOREIGN_INSPECTION_ID),
      description: 'Unrelated defect',
      severity: NonConformitySeverity::LOW,
    ));

    $forInspection = $this->repository->findByInspectionId(
      NonConformityInspectionId::fromString(self::INSPECTION_ID),
    );

    self::assertCount(2, $forInspection);
    self::assertSame(2, $this->repository->countByInspectionId(
      NonConformityInspectionId::fromString(self::INSPECTION_ID),
    ));
    self::assertSame(1, $this->repository->countByInspectionId(
      NonConformityInspectionId::fromString(self::FOREIGN_INSPECTION_ID),
    ));
  }

  #[Test]
  public function testFindByOrganizationIdIsScopedToTheOrganizationViaTheInspectionJoin(): void
  {
    $this->repository->save(NonConformity::create(
      id: NonConformityId::fromString(self::NON_CONFORMITY_ID),
      inspectionId: NonConformityInspectionId::fromString(self::INSPECTION_ID),
      description: 'In-organization defect',
      severity: NonConformitySeverity::HIGH,
    ));
    $this->repository->save(NonConformity::create(
      id: NonConformityId::fromString(self::FOREIGN_NON_CONFORMITY_ID),
      inspectionId: NonConformityInspectionId::fromString(self::FOREIGN_INSPECTION_ID),
      description: 'Foreign-organization defect',
      severity: NonConformitySeverity::HIGH,
    ));

    $inOrganization = $this->repository->findByOrganizationId(
      InspectionOrganizationId::fromString(self::ORGANIZATION_ID),
    );

    self::assertCount(1, $inOrganization);
    self::assertSame(self::NON_CONFORMITY_ID, (string) $inOrganization[0]->id());
    self::assertSame(1, $this->repository->countByOrganizationId(
      InspectionOrganizationId::fromString(self::ORGANIZATION_ID),
    ));
    self::assertSame(1, $this->repository->countByOrganizationId(
      InspectionOrganizationId::fromString(self::OTHER_ORGANIZATION_ID),
    ));
  }

  #[Test]
  public function testCountsByInspectionIdsGroupsPerInspection(): void
  {
    $this->repository->save(NonConformity::create(
      id: NonConformityId::fromString(self::NON_CONFORMITY_ID),
      inspectionId: NonConformityInspectionId::fromString(self::INSPECTION_ID),
      description: 'Defect one',
      severity: NonConformitySeverity::HIGH,
    ));
    $this->repository->save(NonConformity::create(
      id: NonConformityId::fromString(self::SECOND_NON_CONFORMITY_ID),
      inspectionId: NonConformityInspectionId::fromString(self::INSPECTION_ID),
      description: 'Defect two',
      severity: NonConformitySeverity::LOW,
    ));
    $this->repository->save(NonConformity::create(
      id: NonConformityId::fromString(self::FOREIGN_NON_CONFORMITY_ID),
      inspectionId: NonConformityInspectionId::fromString(self::FOREIGN_INSPECTION_ID),
      description: 'Defect three',
      severity: NonConformitySeverity::MEDIUM,
    ));

    $counts = $this->repository->countsByInspectionIds([
      self::INSPECTION_ID,
      self::FOREIGN_INSPECTION_ID,
    ]);

    self::assertSame(2, $counts[self::INSPECTION_ID]);
    self::assertSame(1, $counts[self::FOREIGN_INSPECTION_ID]);
  }

  #[Test]
  public function testCountsByInspectionIdsReturnsEmptyArrayWhenNoIdsRequested(): void
  {
    self::assertSame([], $this->repository->countsByInspectionIds([]));
  }

  private function createInspection(string $id, OrganizationRecord $organization): InspectionRecord
  {
    $inspection = new InspectionRecord();
    $inspection->id = $id;
    $inspection->organization = $organization;
    $inspection->equipmentId = '990e8400-e29b-41d4-a716-446655498888';
    $inspection->inspectorType = 'user';
    $inspection->inspectorName = 'Jane Doe';
    $inspection->result = 'pass';
    $inspection->status = 'draft';
    $inspection->performedAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $inspection->createdAt = $inspection->performedAt;
    $inspection->updatedAt = $inspection->performedAt;

    return $inspection;
  }

  private function createOrganization(string $id, string $name, string $slug): OrganizationRecord
  {
    $organization = new OrganizationRecord();
    $organization->id = $id;
    $organization->name = $name;
    $organization->slug = $slug;
    $organization->ownerUserId = self::OWNER_USER_ID;
    $organization->createdByUserId = self::OWNER_USER_ID;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $organization->updatedAt = $organization->createdAt;

    return $organization;
  }

  private function cleanup(): void
  {
    $connection = $this->entityManager->getConnection();
    $inspectionIds = [self::INSPECTION_ID, self::FOREIGN_INSPECTION_ID];
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
