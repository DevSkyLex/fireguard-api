<?php

declare(strict_types=1);

namespace Tests\Integration\Intervention\Infrastructure\Adapter\Publication;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Application\UseCase\Command\Equipment\CreateEquipment\{CreateEquipmentCommand, CreateEquipmentHandler};
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Facility\Domain\Exception\FacilityHasActiveDependentsException;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Inspection\Infrastructure\Persistence\Doctrine\Record\InspectionRecord;
use Intervention\Infrastructure\Adapter\Publication\DoctrinePublicationAdapter;
use Intervention\Infrastructure\Persistence\Doctrine\Record\{InterventionChangeRecord, InterventionRecord, InterventionWorkItemRecord};
use InvalidArgumentException;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Tests historical references against the complete PostgreSQL publication transaction.
 *
 * @category Adapter Tests
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(DoctrinePublicationAdapter::class)]
final class HistoricalFacilityReferencesPublicationTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = '890e8400-e29b-41d4-a716-446655449001';

  private const string SITE_ID = '890e8400-e29b-41d4-a716-446655449002';

  private const string FACILITY_ID = '890e8400-e29b-41d4-a716-446655449003';

  private const string INTERVENTION_ID = '890e8400-e29b-41d4-a716-446655449004';

  private const string PUBLICATION_ID = '890e8400-e29b-41d4-a716-446655449005';

  private const string EQUIPMENT_ID = '890e8400-e29b-41d4-a716-446655449006';

  private const string INSPECTION_ID = '890e8400-e29b-41d4-a716-446655449007';

  private const string WORK_ITEM_ID = '890e8400-e29b-41d4-a716-446655449008';

  private const string CHANGE_ID = '890e8400-e29b-41d4-a716-446655449009';

  private EntityManagerInterface $entityManager;

  private DoctrinePublicationAdapter $publication;

  protected function setUp(): void
  {
    self::bootKernel();
    $entityManager = self::getContainer()->get('doctrine.orm.main_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
    $this->entityManager = $entityManager;
    $publication = self::getContainer()->get(DoctrinePublicationAdapter::class);
    self::assertInstanceOf(DoctrinePublicationAdapter::class, $publication);
    $this->publication = $publication;
    $this->seed();
  }

  protected function tearDown(): void
  {
    parent::tearDown();
    if ($this->entityManager->isOpen()) {
      $this->entityManager->close();
    }
  }

  #[Test]
  public function itPublishesAnArchiveWhilePreservingCompletedAndTerminalReferences(): void
  {
    $intervention = $this->intervention();
    self::assertNull($intervention->siteId);
    $this->workItem('/api/facilities/' . self::FACILITY_ID);
    $equipment = $this->equipment('decommissioned', 'draft', self::FACILITY_ID);
    $inspection = new InspectionRecord();
    $inspection->id = self::INSPECTION_ID;
    $inspection->organization = $intervention->organization;
    $inspection->interventionId = self::INTERVENTION_ID;
    $inspection->recordStatus = 'draft';
    $inspection->equipmentId = $equipment->id;
    $inspection->facilityId = self::FACILITY_ID;
    $inspection->inspectorType = 'user';
    $inspection->inspectorName = 'Historical inspector';
    $inspection->result = 'compliant';
    $inspection->status = 'closed';
    $inspection->performedAt = new DateTimeImmutable();
    $inspection->createdAt = $inspection->performedAt;
    $inspection->updatedAt = $inspection->performedAt;
    $this->entityManager->persist($inspection);
    $this->change(self::CHANGE_ID, '/api/facilities/' . self::FACILITY_ID, ['status' => 'archived']);
    $this->entityManager->flush();
    $this->queuePublication();

    self::assertTrue($this->publication->publish(self::PUBLICATION_ID));
    $connection = $this->entityManager->getConnection();
    self::assertSame('archived', $connection->fetchOne('SELECT status FROM facilities WHERE id = ?', [self::FACILITY_ID]));
    self::assertSame('published', $connection->fetchOne('SELECT status FROM interventions WHERE id = ?', [self::INTERVENTION_ID]));
    self::assertSame('/api/facilities/' . self::FACILITY_ID, $connection->fetchOne('SELECT target FROM intervention_work_items WHERE id = ?', [self::WORK_ITEM_ID]));
    self::assertSame('published', $connection->fetchOne('SELECT record_status FROM equipment WHERE id = ?', [self::EQUIPMENT_ID]));
    self::assertSame(self::FACILITY_ID, $connection->fetchOne('SELECT facility_id FROM equipment WHERE id = ?', [self::EQUIPMENT_ID]));
    self::assertSame('published', $connection->fetchOne('SELECT record_status FROM inspections WHERE id = ?', [self::INSPECTION_ID]));
    self::assertSame(self::FACILITY_ID, $connection->fetchOne('SELECT facility_id FROM inspections WHERE id = ?', [self::INSPECTION_ID]));
  }

  #[Test]
  public function itRejectsANewAssignmentToAPlaceArchivedByTheSamePublication(): void
  {
    $this->equipment('decommissioned', 'published', null);
    $this->change(self::CHANGE_ID, '/api/facilities/' . self::FACILITY_ID, ['status' => 'archived']);
    $this->change('890e8400-e29b-41d4-a716-446655449010', '/api/equipment/' . self::EQUIPMENT_ID, ['facility' => '/api/facilities/' . self::FACILITY_ID]);
    $this->entityManager->flush();
    $this->queuePublication();
    $connection = $this->entityManager->getConnection();

    try {
      $this->publication->publish(self::PUBLICATION_ID);
      self::fail('An explicitly written assignment must still require an active facility.');
    } catch (InvalidArgumentException) {
      self::assertSame('active', $connection->fetchOne('SELECT status FROM facilities WHERE id = ?', [self::FACILITY_ID]));
      self::assertNull($connection->fetchOne('SELECT facility_id FROM equipment WHERE id = ?', [self::EQUIPMENT_ID]));
      self::assertSame('proposed', $connection->fetchOne('SELECT status FROM intervention_changes WHERE id = ?', [self::CHANGE_ID]));
      self::assertSame('submitted', $connection->fetchOne('SELECT status FROM interventions WHERE id = ?', [self::INTERVENTION_ID]));
    }
  }

  #[Test]
  public function itRejectsCreatingEquipmentWithAnArchivedFacility(): void
  {
    $facility = $this->entityManager->find(FacilityRecord::class, self::FACILITY_ID);
    self::assertInstanceOf(FacilityRecord::class, $facility);
    $facility->status = 'archived';
    $this->entityManager->flush();
    $handler = self::getContainer()->get(CreateEquipmentHandler::class);
    self::assertInstanceOf(CreateEquipmentHandler::class, $handler);
    $connection = $this->entityManager->getConnection();

    try {
      $handler(new CreateEquipmentCommand(
        organizationId: self::ORGANIZATION_ID,
        type: 'fire_extinguisher',
        resourceId: self::EQUIPMENT_ID,
        facilityId: self::FACILITY_ID,
      ));
      self::fail('Creating a resource must continue to require an active facility.');
    } catch (InvalidArgumentException) {
      self::assertSame(0, $connection->fetchOne('SELECT COUNT(*) FROM equipment WHERE id = ?', [self::EQUIPMENT_ID]));
      self::assertSame('archived', $connection->fetchOne('SELECT status FROM facilities WHERE id = ?', [self::FACILITY_ID]));
    }
  }

  #[Test]
  public function itStillRejectsArchivingTheSiteOfAnActiveIntervention(): void
  {
    $this->intervention()->siteId = self::FACILITY_ID;
    $this->workItem('/api/facilities/' . self::FACILITY_ID);
    $this->equipment('in_stock', 'draft', null);
    $this->change(self::CHANGE_ID, '/api/facilities/' . self::FACILITY_ID, ['status' => 'archived']);
    $this->entityManager->flush();
    $this->queuePublication();
    $connection = $this->entityManager->getConnection();

    try {
      $this->publication->publish(self::PUBLICATION_ID);
      self::fail('The submitted intervention remains an active site dependency.');
    } catch (FacilityHasActiveDependentsException) {
      self::assertSame('active', $connection->fetchOne('SELECT status FROM facilities WHERE id = ?', [self::FACILITY_ID]));
      self::assertSame('draft', $connection->fetchOne('SELECT record_status FROM equipment WHERE id = ?', [self::EQUIPMENT_ID]));
      self::assertSame('submitted', $connection->fetchOne('SELECT status FROM interventions WHERE id = ?', [self::INTERVENTION_ID]));
      self::assertSame(self::FACILITY_ID, $connection->fetchOne('SELECT site_id FROM interventions WHERE id = ?', [self::INTERVENTION_ID]));
    }
  }

  /**
   * @return iterable<string, array{string}>
   */
  public static function unavailableReferences(): iterable
  {
    yield 'missing facility' => ['missing'];
    yield 'another interventions draft' => ['foreign_draft'];
    yield 'another organizations facility' => ['foreign_organization'];
  }

  #[Test]
  #[DataProvider('unavailableReferences')]
  public function itRejectsUnavailableHistoricalTargetsWithoutPublishingDrafts(string $kind): void
  {
    $facility = $this->entityManager->find(FacilityRecord::class, self::FACILITY_ID);
    self::assertInstanceOf(FacilityRecord::class, $facility);
    $target = self::FACILITY_ID;
    if ('missing' === $kind) {
      $target = '890e8400-e29b-41d4-a716-446655449099';
    } elseif ('foreign_draft' === $kind) {
      $facility->recordStatus = 'draft';
      $facility->interventionId = '890e8400-e29b-41d4-a716-446655449098';
    } else {
      $foreign = $this->organization('890e8400-e29b-41d4-a716-446655449097');
      $facility->organization = $foreign;
      $facility->parentFacility = null;
      $facility->type = 'site';
    }
    $this->workItem('/api/facilities/' . $target);
    $this->equipment('in_stock', 'draft', null);
    $this->entityManager->flush();
    $this->queuePublication();
    $connection = $this->entityManager->getConnection();

    try {
      $this->publication->publish(self::PUBLICATION_ID);
      self::fail('Historical links must still resolve within organization and publication scope.');
    } catch (InvalidArgumentException) {
      self::assertSame('draft', $connection->fetchOne('SELECT record_status FROM equipment WHERE id = ?', [self::EQUIPMENT_ID]));
      self::assertSame(1, $connection->fetchOne('SELECT revision FROM equipment WHERE id = ?', [self::EQUIPMENT_ID]));
      self::assertSame('submitted', $connection->fetchOne('SELECT status FROM interventions WHERE id = ?', [self::INTERVENTION_ID]));
      self::assertSame('/api/facilities/' . $target, $connection->fetchOne('SELECT target FROM intervention_work_items WHERE id = ?', [self::WORK_ITEM_ID]));
    }
  }

  private function seed(): void
  {
    $organization = $this->organization(self::ORGANIZATION_ID);
    $site = new FacilityRecord();
    $site->id = self::SITE_ID;
    $site->organization = $organization;
    $site->name = 'Historical reference site';
    $site->type = 'site';
    $site->createdAt = new DateTimeImmutable();
    $site->updatedAt = $site->createdAt;
    $this->entityManager->persist($site);
    $facility = new FacilityRecord();
    $facility->id = self::FACILITY_ID;
    $facility->organization = $organization;
    $facility->parentFacility = $site;
    $facility->name = 'Historical reference building';
    $facility->type = 'building';
    $facility->createdAt = new DateTimeImmutable();
    $facility->updatedAt = $facility->createdAt;
    $this->entityManager->persist($facility);
    $intervention = new InterventionRecord();
    $intervention->id = self::INTERVENTION_ID;
    $intervention->organization = $organization;
    $intervention->name = 'Historical reference publication';
    $intervention->number = 9901;
    $intervention->status = 'submitted';
    $intervention->createdAt = new DateTimeImmutable();
    $intervention->updatedAt = $intervention->createdAt;
    $this->entityManager->persist($intervention);
    $this->entityManager->flush();
  }

  private function organization(string $id): OrganizationRecord
  {
    $organization = new OrganizationRecord();
    $organization->id = $id;
    $organization->name = 'Historical reference organization';
    $organization->slug = 'historical-reference-' . $id;
    $organization->ownerUserId = self::ORGANIZATION_ID;
    $organization->createdByUserId = self::ORGANIZATION_ID;
    $organization->createdAt = new DateTimeImmutable();
    $organization->updatedAt = $organization->createdAt;
    $this->entityManager->persist($organization);

    return $organization;
  }

  private function intervention(): InterventionRecord
  {
    $intervention = $this->entityManager->find(InterventionRecord::class, self::INTERVENTION_ID);
    self::assertInstanceOf(InterventionRecord::class, $intervention);

    return $intervention;
  }

  private function workItem(string $target): void
  {
    $item = new InterventionWorkItemRecord();
    $item->id = self::WORK_ITEM_ID;
    $item->intervention = $this->intervention();
    $item->action = 'archive';
    $item->status = 'completed';
    $item->target = $target;
    $item->resultResource = $target;
    $item->createdAt = new DateTimeImmutable();
    $item->updatedAt = $item->createdAt;
    $this->entityManager->persist($item);
  }

  private function equipment(string $status, string $publicationState, ?string $facilityId): EquipmentRecord
  {
    $equipment = new EquipmentRecord();
    $equipment->id = self::EQUIPMENT_ID;
    $equipment->organization = $this->intervention()->organization;
    $equipment->type = 'fire_extinguisher';
    $equipment->status = $status;
    $equipment->recordStatus = $publicationState;
    $equipment->interventionId = 'draft' === $publicationState ? self::INTERVENTION_ID : null;
    $equipment->facilityId = $facilityId;
    $equipment->createdAt = new DateTimeImmutable();
    $equipment->updatedAt = $equipment->createdAt;
    $this->entityManager->persist($equipment);

    return $equipment;
  }

  /**
   * @param array<string, mixed> $patch the proposed publication changes
   */
  private function change(string $id, string $resource, array $patch): void
  {
    $change = new InterventionChangeRecord();
    $change->id = $id;
    $change->intervention = $this->intervention();
    $change->resource = $resource;
    $change->patch = $patch;
    $change->createdAt = new DateTimeImmutable();
    $change->updatedAt = $change->createdAt;
    $this->entityManager->persist($change);
  }

  private function queuePublication(): void
  {
    $this->publication->createOrGetPending(self::PUBLICATION_ID, self::INTERVENTION_ID, 1);
    $this->publication->markProcessing(self::PUBLICATION_ID);
  }
}
