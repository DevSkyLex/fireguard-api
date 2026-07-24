<?php

declare(strict_types=1);

namespace Tests\Integration\Inspection\Infrastructure\Adapter\Intervention;

use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Inspection\Infrastructure\Adapter\Intervention\InspectionInterventionResourceAdapter;
use Inspection\Infrastructure\Persistence\Doctrine\Record\InspectionRecord;
use Intervention\Domain\ValueObject\InterventionResourceType;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test InspectionInterventionResourceAdapter.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InspectionInterventionResourceAdapter::class)]
final class InspectionInterventionResourceAdapterTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = 'e20e8400-e29b-41d4-a716-446655e20001';

  private const string OTHER_ORGANIZATION_ID = 'e20e8400-e29b-41d4-a716-446655e20002';

  private const string INSPECTION_ONE = 'e20e8400-e29b-41d4-a716-446655e21001';

  private const string INSPECTION_TWO = 'e20e8400-e29b-41d4-a716-446655e21002';

  private const string INSPECTION_FOREIGN = 'e20e8400-e29b-41d4-a716-446655e21003';

  private const string INSPECTION_UNASSIGNED = 'e20e8400-e29b-41d4-a716-446655e21004';

  private const string INTERVENTION_ONE = 'e20e8400-e29b-41d4-a716-446655e2f001';

  private const string INTERVENTION_TWO = 'e20e8400-e29b-41d4-a716-446655e2f002';

  private const string INTERVENTION_NEW = 'e20e8400-e29b-41d4-a716-446655e2f003';

  private const string CLIENT_ID = 'e20e8400-e29b-41d4-a716-446655e2c001';

  private const string CLIENT_ID_NEW = 'e20e8400-e29b-41d4-a716-446655e2c002';

  private const string EQUIPMENT_ID = 'e20e8400-e29b-41d4-a716-446655e28001';

  private const string UNKNOWN_ID = 'e20e8400-e29b-41d4-a716-446655e2ffff';

  private const string OWNER_USER_ID = 'e20e8400-e29b-41d4-a716-446655e29000';

  private EntityManagerInterface $entityManager;

  private InspectionInterventionResourceAdapter $adapter;

  protected function setUp(): void
  {
    self::bootKernel();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();

    $this->adapter = new InspectionInterventionResourceAdapter($this->entityManager);

    $organization = $this->createOrganization(self::ORGANIZATION_ID, 'Intervention Resource Org', 'intervention-resource-org');
    $otherOrganization = $this->createOrganization(self::OTHER_ORGANIZATION_ID, 'Intervention Resource Org B', 'intervention-resource-org-b');
    $this->entityManager->persist($organization);
    $this->entityManager->persist($otherOrganization);

    // Two inspections attached to the same intervention in the primary org.
    $this->entityManager->persist($this->createInspection(
      id: self::INSPECTION_ONE,
      organization: $organization,
      interventionId: self::INTERVENTION_ONE,
      clientId: self::CLIENT_ID,
    ));
    $this->entityManager->persist($this->createInspection(
      id: self::INSPECTION_TWO,
      organization: $organization,
      interventionId: self::INTERVENTION_ONE,
      clientId: null,
    ));
    // One inspection attached to a different intervention in the other org.
    $this->entityManager->persist($this->createInspection(
      id: self::INSPECTION_FOREIGN,
      organization: $otherOrganization,
      interventionId: self::INTERVENTION_TWO,
      clientId: null,
    ));
    // A standalone published inspection with no intervention yet, for assign().
    $this->entityManager->persist($this->createInspection(
      id: self::INSPECTION_UNASSIGNED,
      organization: $organization,
      interventionId: null,
      clientId: null,
    ));

    $this->entityManager->flush();
  }

  protected function tearDown(): void
  {
    $this->cleanup();
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testSupportsInspectionIri(): void
  {
    self::assertTrue($this->adapter->supports('/api/inspections/' . self::INSPECTION_ONE));
    self::assertFalse($this->adapter->supports('/api/equipment/' . self::INSPECTION_ONE));
    self::assertFalse($this->adapter->supports('/api/inspections/' . self::INSPECTION_ONE . '/responses'));
  }

  #[Test]
  public function testSupportsInspectionResourceType(): void
  {
    self::assertTrue($this->adapter->supportsResourceType(InterventionResourceType::INSPECTION));
    self::assertFalse($this->adapter->supportsResourceType(InterventionResourceType::EQUIPMENT));
    self::assertFalse($this->adapter->supportsResourceType(InterventionResourceType::FACILITY));
  }

  #[Test]
  public function testResourceExistsReflectsPersistedRecords(): void
  {
    self::assertTrue($this->adapter->resourceExists(self::INSPECTION_ONE));
    self::assertFalse($this->adapter->resourceExists(self::UNKNOWN_ID));
  }

  #[Test]
  public function testResourceBelongsToOrganizationIsScoped(): void
  {
    self::assertTrue($this->adapter->resourceBelongsToOrganization(self::INSPECTION_ONE, self::ORGANIZATION_ID));
    self::assertFalse($this->adapter->resourceBelongsToOrganization(self::INSPECTION_ONE, self::OTHER_ORGANIZATION_ID));
    self::assertFalse($this->adapter->resourceBelongsToOrganization(self::UNKNOWN_ID, self::ORGANIZATION_ID));
  }

  #[Test]
  public function testClientIdExists(): void
  {
    self::assertTrue($this->adapter->clientIdExists(self::CLIENT_ID));
    self::assertFalse($this->adapter->clientIdExists(self::UNKNOWN_ID));
  }

  #[Test]
  public function testCountForIntervention(): void
  {
    self::assertSame(2, $this->adapter->countForIntervention(self::INTERVENTION_ONE));
    self::assertSame(1, $this->adapter->countForIntervention(self::INTERVENTION_TWO));
    self::assertSame(0, $this->adapter->countForIntervention(self::INTERVENTION_NEW));
  }

  #[Test]
  public function testCountsForInterventionsGroupsPerIntervention(): void
  {
    $counts = $this->adapter->countsForInterventions([self::INTERVENTION_ONE, self::INTERVENTION_TWO]);

    self::assertSame(2, $counts[self::INTERVENTION_ONE]);
    self::assertSame(1, $counts[self::INTERVENTION_TWO]);
    self::assertSame([], $this->adapter->countsForInterventions([]));
  }

  #[Test]
  public function testBlockerCountsForInterventionsIsAlwaysEmpty(): void
  {
    self::assertSame([], $this->adapter->blockerCountsForInterventions([self::INTERVENTION_ONE]));
  }

  #[Test]
  public function testAssignAttachesInterventionAndMarksRecordAsDraft(): void
  {
    $assignment = $this->adapter->assign(self::INSPECTION_UNASSIGNED, self::INTERVENTION_NEW, self::CLIENT_ID_NEW);

    self::assertSame(self::INTERVENTION_NEW, $assignment->interventionId);
    self::assertSame('draft', $assignment->recordStatus);
    self::assertSame(1, $assignment->revision);

    $this->entityManager->clear();
    $record = $this->entityManager->find(InspectionRecord::class, self::INSPECTION_UNASSIGNED);
    self::assertInstanceOf(InspectionRecord::class, $record);
    self::assertSame(self::INTERVENTION_NEW, $record->interventionId);
    self::assertSame(self::CLIENT_ID_NEW, $record->clientId);
    self::assertSame('draft', $record->recordStatus);
  }

  #[Test]
  public function testAssignWithoutInterventionPublishesRecord(): void
  {
    $assignment = $this->adapter->assign(self::INSPECTION_UNASSIGNED, null, null);

    self::assertNull($assignment->interventionId);
    self::assertSame('published', $assignment->recordStatus);

    $this->entityManager->clear();
    $record = $this->entityManager->find(InspectionRecord::class, self::INSPECTION_UNASSIGNED);
    self::assertInstanceOf(InspectionRecord::class, $record);
    self::assertNull($record->interventionId);
    self::assertSame('published', $record->recordStatus);
  }

  private function createInspection(
    string $id,
    OrganizationRecord $organization,
    ?string $interventionId,
    ?string $clientId,
  ): InspectionRecord {
    $inspection = new InspectionRecord();
    $inspection->id = $id;
    $inspection->organization = $organization;
    $inspection->interventionId = $interventionId;
    $inspection->clientId = $clientId;
    $inspection->recordStatus = 'published';
    $inspection->equipmentId = self::EQUIPMENT_ID;
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
    $inspectionIds = [
      self::INSPECTION_ONE,
      self::INSPECTION_TWO,
      self::INSPECTION_FOREIGN,
      self::INSPECTION_UNASSIGNED,
    ];
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
