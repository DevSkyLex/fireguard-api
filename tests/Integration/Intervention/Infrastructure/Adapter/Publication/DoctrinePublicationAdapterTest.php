<?php

declare(strict_types=1);

namespace Tests\Integration\Intervention\Infrastructure\Adapter\Publication;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Intervention\Application\Contract\Resource\{InterventionResourceSummary, InterventionWorkItemSummary};
use Intervention\Application\Port\Outbound\{InterventionAttachmentRepositoryPort, InterventionResourceGatewayPort};
use Intervention\Application\Service\InterventionIssueFinder;
use Intervention\Application\UseCase\Command\Publication\ExecutePublication\{ExecutePublicationCommand, ExecutePublicationHandler};
use Intervention\Domain\Event\Publication\{InterventionPublicationFailedEvent, InterventionPublishedEvent};
use Intervention\Domain\Exception\{InterventionConflictException, InterventionNotFoundException, PublicationNotFoundException};
use Intervention\Infrastructure\Adapter\Publication\DoctrinePublicationAdapter;
use Intervention\Infrastructure\Persistence\Doctrine\Record\{InterventionChangeRecord, InterventionRecord, PublicationRecord};
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use RuntimeException;
use Shared\Application\Port\Outbound\EventDispatcherPort;
use Shared\Infrastructure\Messaging\Outbox\{DbalTransactionManagerAdapter, DeliverOutboxEventHandler, OutboxEvent, TransactionalEventDispatcher};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

use function sprintf;

/**
 * Test DoctrinePublicationAdapterTest.
 *
 * Exercises the publication port against the real main database: the
 * pending/processing/completed/failed lifecycle, its idempotent replay
 * guarantees, and the transactional publish that applies proposed changes
 * before flipping the intervention to published.
 *
 * @category Adapter Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(DoctrinePublicationAdapter::class)]
final class DoctrinePublicationAdapterTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = '880e8400-e29b-41d4-a716-4466554490a0';

  private const string OWNER_USER_ID = '880e8400-e29b-41d4-a716-4466554490a1';

  private const string RESPONSIBLE_MEMBER_ID = '880e8400-e29b-41d4-a716-4466554490a2';

  private const string FACILITY_ID = '880e8400-e29b-41d4-a716-4466554490a3';

  private const string INTERVENTION_ID = '880e8400-e29b-41d4-a716-4466554490b0';

  private const string PUBLICATION_ID = '880e8400-e29b-41d4-a716-4466554490c0';

  private const string CHANGE_ID = '880e8400-e29b-41d4-a716-4466554490d0';

  private const string ORPHAN_PUBLICATION_ID = '880e8400-e29b-41d4-a716-4466554490e0';

  private const string MISSING_ID = '880e8400-e29b-41d4-a716-4466554490ff';

  private EntityManagerInterface $entityManager;

  private DoctrinePublicationAdapter $adapter;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    /** @var DoctrinePublicationAdapter $adapter */
    $adapter = static::getContainer()->get(DoctrinePublicationAdapter::class);
    $this->adapter = $adapter;

    $this->seedOrganizationAndIntervention();
  }

  protected function tearDown(): void
  {
    parent::tearDown();
    if ($this->entityManager->isOpen()) {
      $this->entityManager->close();
    }
  }

  /**
   * Method testDiscardPreservesOrderedTypedReferencesAndExcludesForeignOrRemovedOwners.
   *
   * Exercises all three dependency reads and exact IRI matching against the real main database.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testDiscardPreservesOrderedTypedReferencesAndExcludesForeignOrRemovedOwners(): void
  {
    $draft = $this->hierarchyFacility('880e8400-e29b-41d4-a716-446655449151', 'building', self::FACILITY_ID, true);
    $intervention = $this->entityManager->find(InterventionRecord::class, self::INTERVENTION_ID);
    self::assertInstanceOf(InterventionRecord::class, $intervention);
    self::assertInstanceOf(OrganizationRecord::class, $intervention->organization);
    $intervention->siteId = $draft->id;
    $facilityIri = '/api/facilities/' . $draft->id;
    $equipmentId = '880e8400-e29b-41d4-a716-446655449152';
    $equipmentIri = '/api/equipment/' . $equipmentId;
    $inspectionId = '880e8400-e29b-41d4-a716-446655449153';
    $inspectionIri = '/api/inspections/' . $inspectionId;
    $item = new \Intervention\Infrastructure\Persistence\Doctrine\Record\InterventionWorkItemRecord();
    $item->id = '880e8400-e29b-41d4-a716-446655449154';
    $item->intervention = $intervention;
    $item->action = 'create';
    $item->target = $facilityIri;
    $item->resultResource = $equipmentIri;
    $item->createdAt = new DateTimeImmutable();
    $item->updatedAt = $item->createdAt;
    $this->entityManager->persist($item);
    $change = new InterventionChangeRecord();
    $change->id = '880e8400-e29b-41d4-a716-446655449155';
    $change->intervention = $intervention;
    $change->resource = $facilityIri;
    $change->patch = ['facility' => $facilityIri, 'parent' => ['invalid' => $facilityIri], 'equipment' => $equipmentIri, 'inspection' => $inspectionIri];
    $change->createdAt = $item->createdAt;
    $change->updatedAt = $item->createdAt;
    $this->entityManager->persist($change);
    $appliedChange = clone $change;
    $appliedChange->id = '880e8400-e29b-41d4-a716-446655449156';
    $appliedChange->status = 'applied';
    $this->entityManager->persist($appliedChange);
    $foreignOrganization = clone $intervention->organization;
    $foreignOrganization->id = '880e8400-e29b-41d4-a716-446655449157';
    $foreignOrganization->slug = 'foreign-discard-reference';
    $this->entityManager->persist($foreignOrganization);
    $foreignIntervention = clone $intervention;
    $foreignIntervention->id = '880e8400-e29b-41d4-a716-446655449158';
    $foreignIntervention->organization = $foreignOrganization;
    $this->entityManager->persist($foreignIntervention);
    $foreignItem = clone $item;
    $foreignItem->id = '880e8400-e29b-41d4-a716-446655449159';
    $foreignItem->intervention = $foreignIntervention;
    $this->entityManager->persist($foreignItem);
    $foreignChange = clone $change;
    $foreignChange->id = '880e8400-e29b-41d4-a716-44665544915a';
    $foreignChange->intervention = $foreignIntervention;
    $this->entityManager->persist($foreignChange);
    $this->entityManager->flush();
    $drafts = $this->createStub(\Facility\Application\Port\Inbound\FacilityDraftReferencesPort::class);
    $drafts->method('draftIds')->willReturn([$draft->id]);
    $resources = $this->createStub(\Intervention\Application\Port\Inbound\InterventionDraftResourcesPort::class);
    $resources->method('draftResourceIris')->willReturn([$facilityIri, $equipmentIri, $inspectionIri]);
    $guard = new \Intervention\Infrastructure\Adapter\Resource\InterventionDraftReferenceGuardAdapter($this->entityManager, $drafts, draftResources: $resources);

    try {
      $guard->assertCanDiscard(self::INTERVENTION_ID);
      self::fail('Retained resources must block discarding their referenced drafts.');
    } catch (\Intervention\Application\Contract\Resource\InterventionDraftDependencyConflict $exception) {
      self::assertSame([
        ['resourceType' => 'intervention', 'resourceId' => self::INTERVENTION_ID, 'relatedResourceId' => $draft->id],
        ['resourceType' => 'work_item', 'resourceId' => $item->id, 'relatedResourceId' => $draft->id],
        ['resourceType' => 'work_item', 'resourceId' => $item->id, 'relatedResourceId' => $equipmentId],
        ['resourceType' => 'change', 'resourceId' => $change->id, 'relatedResourceId' => $draft->id],
        ['resourceType' => 'change', 'resourceId' => $change->id, 'relatedResourceId' => $draft->id],
        ['resourceType' => 'change', 'resourceId' => $change->id, 'relatedResourceId' => $equipmentId],
        ['resourceType' => 'change', 'resourceId' => $change->id, 'relatedResourceId' => $inspectionId],
      ], $exception->references);
    }
    $guard->assertCanDiscard(self::INTERVENTION_ID, false);
    self::assertSame(1, $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM facilities WHERE id = ?', [$draft->id]));
  }

  #[Test]
  public function testPublicationValidatesDraftsAndProposedParentsAsOneFinalGraph(): void
  {
    $building = $this->hierarchyFacility('880e8400-e29b-41d4-a716-446655449101', 'building', self::FACILITY_ID, true);
    $floor = $this->hierarchyFacility('880e8400-e29b-41d4-a716-446655449102', 'floor', $building->id, true);
    $zone = $this->hierarchyFacility('880e8400-e29b-41d4-a716-446655449103', 'zone', self::FACILITY_ID, false);
    $this->hierarchyChange('880e8400-e29b-41d4-a716-446655449104', $zone->id, ['parent' => '/api/facilities/' . $floor->id]);
    $this->entityManager->flush();
    $this->adapter->createOrGetPending(self::PUBLICATION_ID, self::INTERVENTION_ID, 1);
    $this->adapter->markProcessing(self::PUBLICATION_ID);
    self::assertTrue($this->adapter->publish(self::PUBLICATION_ID));
    $connection = $this->entityManager->getConnection();
    self::assertSame('published', $connection->fetchOne('SELECT record_status FROM facilities WHERE id = ?', [$floor->id]));
    self::assertSame($floor->id, $connection->fetchOne('SELECT parent_facility_id FROM facilities WHERE id = ?', [$zone->id]));
    self::assertSame(2, $connection->fetchOne('SELECT revision FROM facilities WHERE id = ?', [$zone->id]));
  }

  #[Test]
  public function testPublicationRejectsAMergedCycleWithoutPublishingAnyDraft(): void
  {
    $first = $this->hierarchyFacility('880e8400-e29b-41d4-a716-446655449111', 'zone', self::FACILITY_ID, false);
    $second = $this->hierarchyFacility('880e8400-e29b-41d4-a716-446655449112', 'zone', self::FACILITY_ID, false);
    $draft = $this->hierarchyFacility('880e8400-e29b-41d4-a716-446655449113', 'building', self::FACILITY_ID, true);
    $this->hierarchyChange('880e8400-e29b-41d4-a716-446655449114', $first->id, ['parent' => '/api/facilities/' . $second->id]);
    $this->hierarchyChange('880e8400-e29b-41d4-a716-446655449115', $second->id, ['parent' => '/api/facilities/' . $first->id]);
    $this->entityManager->flush();
    $this->adapter->createOrGetPending(self::PUBLICATION_ID, self::INTERVENTION_ID, 1);
    $connection = $this->entityManager->getConnection();

    try {
      $this->adapter->publish(self::PUBLICATION_ID);
      self::fail('A cycle in the combined proposals must reject publication.');
    } catch (\Facility\Domain\Exception\FacilityHierarchyException) {
      self::assertSame('draft', $connection->fetchOne('SELECT record_status FROM facilities WHERE id = ?', [$draft->id]));
      self::assertSame(self::FACILITY_ID, $connection->fetchOne('SELECT parent_facility_id FROM facilities WHERE id = ?', [$first->id]));
      self::assertSame('submitted', $connection->fetchOne('SELECT status FROM interventions WHERE id = ?', [self::INTERVENTION_ID]));
    }
  }

  #[Test]
  public function testDiscardRefusesPublishedEquipmentReferencesBeforeDeletingFacilities(): void
  {
    $draft = $this->hierarchyFacility('880e8400-e29b-41d4-a716-446655449121', 'building', self::FACILITY_ID, true);
    $equipment = new \Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord();
    $equipment->id = '880e8400-e29b-41d4-a716-446655449122';
    $equipment->organization = $draft->organization;
    $equipment->facilityId = $draft->id;
    $equipment->type = 'fire_extinguisher';
    $equipment->createdAt = new DateTimeImmutable();
    $equipment->updatedAt = $equipment->createdAt;
    $this->entityManager->persist($equipment);
    $this->entityManager->flush();
    $publisher = self::getContainer()->get(\Intervention\Application\Service\InterventionDraftPublisher::class);
    self::assertInstanceOf(\Intervention\Application\Service\InterventionDraftPublisher::class, $publisher);

    try {
      $publisher->discard(self::INTERVENTION_ID);
      self::fail('A retained equipment must prevent discarding its draft facility.');
    } catch (\Intervention\Application\Contract\Resource\InterventionDraftDependencyConflict $exception) {
      self::assertSame('equipment', $exception->references[0]['resourceType']);
      self::assertSame(1, $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM facilities WHERE id = ?', [$draft->id]));
      self::assertSame($draft->id, $this->entityManager->getConnection()->fetchOne('SELECT facility_id FROM equipment WHERE id = ?', [$equipment->id]));
    }
  }

  #[Test]
  public function testDiscardProtectsTheAbandonedInterventionsOwnRetainedTargets(): void
  {
    $draft = $this->hierarchyFacility('880e8400-e29b-41d4-a716-446655449131', 'building', self::FACILITY_ID, true);
    $intervention = $this->entityManager->find(InterventionRecord::class, self::INTERVENTION_ID);
    self::assertInstanceOf(InterventionRecord::class, $intervention);
    $item = new \Intervention\Infrastructure\Persistence\Doctrine\Record\InterventionWorkItemRecord();
    $item->id = '880e8400-e29b-41d4-a716-446655449132';
    $item->intervention = $intervention;
    $item->action = 'create';
    $item->target = '/api/facilities/' . $draft->id;
    $item->createdAt = new DateTimeImmutable();
    $item->updatedAt = $item->createdAt;
    $this->entityManager->persist($item);
    $this->entityManager->flush();
    $validation = self::getContainer()->get(\Intervention\Application\Service\InterventionPublicationValidation::class);
    self::assertInstanceOf(\Intervention\Application\Service\InterventionPublicationValidation::class, $validation);
    // A complete intervention deletion removes this work item by cascade.
    $validation->assertCanDiscard(self::INTERVENTION_ID, false);
    $publisher = self::getContainer()->get(\Intervention\Application\Service\InterventionDraftPublisher::class);
    self::assertInstanceOf(\Intervention\Application\Service\InterventionDraftPublisher::class, $publisher);

    try {
      $publisher->discard(self::INTERVENTION_ID);
      self::fail('Abandonment must preserve drafts referenced by retained work items.');
    } catch (\Intervention\Application\Contract\Resource\InterventionDraftDependencyConflict $exception) {
      self::assertSame('work_item', $exception->references[0]['resourceType']);
      self::assertSame(1, $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM facilities WHERE id = ?', [$draft->id]));
      self::assertSame($item->target, $this->entityManager->getConnection()->fetchOne('SELECT target FROM intervention_work_items WHERE id = ?', [$item->id]));
    }
  }

  #[Test]
  public function testDiscardProtectsRetainedInspectionReferences(): void
  {
    $draft = $this->hierarchyFacility('880e8400-e29b-41d4-a716-446655449141', 'building', self::FACILITY_ID, true);
    $inspection = new \Inspection\Infrastructure\Persistence\Doctrine\Record\InspectionRecord();
    $inspection->id = '880e8400-e29b-41d4-a716-446655449142';
    $inspection->organization = $draft->organization;
    $inspection->equipmentId = '880e8400-e29b-41d4-a716-446655449143';
    $inspection->facilityId = $draft->id;
    $inspection->inspectorType = 'user';
    $inspection->inspectorName = 'Draft reference test';
    $inspection->result = 'compliant';
    $inspection->status = 'closed';
    $inspection->performedAt = new DateTimeImmutable();
    $inspection->createdAt = $inspection->performedAt;
    $inspection->updatedAt = $inspection->performedAt;
    $this->entityManager->persist($inspection);
    $this->entityManager->flush();
    $publisher = self::getContainer()->get(\Intervention\Application\Service\InterventionDraftPublisher::class);
    self::assertInstanceOf(\Intervention\Application\Service\InterventionDraftPublisher::class, $publisher);

    try {
      $publisher->discard(self::INTERVENTION_ID);
      self::fail('A retained inspection must preserve its referenced draft facility.');
    } catch (\Intervention\Application\Contract\Resource\InterventionDraftDependencyConflict $exception) {
      self::assertSame('inspection', $exception->references[0]['resourceType']);
      self::assertSame(1, $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM facilities WHERE id = ?', [$draft->id]));
      self::assertSame($draft->id, $this->entityManager->getConnection()->fetchOne('SELECT facility_id FROM inspections WHERE id = ?', [$inspection->id]));
    }
  }

  #[Test]
  public function testDiscardProtectsAnInspectionOfDraftEquipmentWithoutDraftFacilities(): void
  {
    $organization = $this->entityManager->find(OrganizationRecord::class, self::ORGANIZATION_ID);
    self::assertInstanceOf(OrganizationRecord::class, $organization);
    $equipment = new \Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord();
    $equipment->id = '880e8400-e29b-41d4-a716-446655449151';
    $equipment->organization = $organization;
    $equipment->interventionId = self::INTERVENTION_ID;
    $equipment->recordStatus = 'draft';
    $equipment->type = 'fire_extinguisher';
    $equipment->createdAt = new DateTimeImmutable();
    $equipment->updatedAt = $equipment->createdAt;
    $this->entityManager->persist($equipment);
    $inspection = $this->retainedInspection('880e8400-e29b-41d4-a716-446655449152', $equipment->id, $organization);
    $this->entityManager->flush();
    $publisher = self::getContainer()->get(\Intervention\Application\Service\InterventionDraftPublisher::class);
    self::assertInstanceOf(\Intervention\Application\Service\InterventionDraftPublisher::class, $publisher);

    try {
      $publisher->discard(self::INTERVENTION_ID);
      self::fail('A retained inspection must preserve its draft equipment.');
    } catch (\Intervention\Application\Contract\Resource\InterventionDraftDependencyConflict $exception) {
      self::assertSame('inspection', $exception->references[0]['resourceType']);
      self::assertSame($equipment->id, $this->entityManager->getConnection()->fetchOne('SELECT equipment_id FROM inspections WHERE id = ?', [$inspection->id]));
      self::assertSame('draft', $this->entityManager->getConnection()->fetchOne('SELECT record_status FROM equipment WHERE id = ?', [$equipment->id]));
    }
  }

  #[Test]
  public function testDiscardProtectsAResponseOfDraftInspection(): void
  {
    $organization = $this->entityManager->find(OrganizationRecord::class, self::ORGANIZATION_ID);
    self::assertInstanceOf(OrganizationRecord::class, $organization);
    $inspection = $this->retainedInspection('880e8400-e29b-41d4-a716-446655449161', self::FACILITY_ID, $organization);
    $inspection->interventionId = self::INTERVENTION_ID;
    $inspection->recordStatus = 'draft';
    $response = new \Inspection\Infrastructure\Persistence\Doctrine\Record\InspectionResponseRecord();
    $response->id = '880e8400-e29b-41d4-a716-446655449162';
    $response->organization = $organization;
    $response->inspectionId = $inspection->id;
    $response->itemKey = 'draft-dependency';
    $response->createdAt = new DateTimeImmutable();
    $response->updatedAt = $response->createdAt;
    $this->entityManager->persist($response);
    $this->entityManager->flush();
    $publisher = self::getContainer()->get(\Intervention\Application\Service\InterventionDraftPublisher::class);
    self::assertInstanceOf(\Intervention\Application\Service\InterventionDraftPublisher::class, $publisher);

    try {
      $publisher->discard(self::INTERVENTION_ID);
      self::fail('A retained response must preserve its draft inspection.');
    } catch (\Intervention\Application\Contract\Resource\InterventionDraftDependencyConflict $exception) {
      self::assertSame('inspection_response', $exception->references[0]['resourceType']);
      self::assertSame(1, $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM inspections WHERE id = ?', [$inspection->id]));
      self::assertSame($inspection->id, $this->entityManager->getConnection()->fetchOne('SELECT inspection_id FROM inspection_responses WHERE id = ?', [$response->id]));
    }
  }

  #[Test]
  public function testPublicationArchivesTheParentAfterApplyingItsChildsLaterMove(): void
  {
    $parent = $this->hierarchyFacility('880e8400-e29b-41d4-a716-446655449171', 'building', self::FACILITY_ID, false);
    $destination = $this->hierarchyFacility('880e8400-e29b-41d4-a716-446655449172', 'building', self::FACILITY_ID, false);
    $child = $this->hierarchyFacility('880e8400-e29b-41d4-a716-446655449173', 'floor', $parent->id, false);
    $this->hierarchyChange('880e8400-e29b-41d4-a716-446655449174', $parent->id, ['status' => 'archived']);
    $this->hierarchyChange('880e8400-e29b-41d4-a716-446655449175', $child->id, ['parent' => '/api/facilities/' . $destination->id]);
    $this->entityManager->flush();
    $this->adapter->createOrGetPending(self::PUBLICATION_ID, self::INTERVENTION_ID, 1);
    $this->adapter->markProcessing(self::PUBLICATION_ID);
    self::assertTrue($this->adapter->publish(self::PUBLICATION_ID));
    $connection = $this->entityManager->getConnection();
    self::assertSame('archived', $connection->fetchOne('SELECT status FROM facilities WHERE id = ?', [$parent->id]));
    self::assertSame($destination->id, $connection->fetchOne('SELECT parent_facility_id FROM facilities WHERE id = ?', [$child->id]));
    self::assertSame(2, $connection->fetchOne('SELECT revision FROM facilities WHERE id = ?', [$parent->id]));
    self::assertSame(2, $connection->fetchOne('SELECT revision FROM facilities WHERE id = ?', [$child->id]));
  }

  #[Test]
  public function testPublicationRefusesArchivingRetainedActiveDependentsAndRollsBackTheWholePublication(): void
  {
    $parent = $this->hierarchyFacility('880e8400-e29b-41d4-a716-446655449181', 'building', self::FACILITY_ID, false);
    $child = $this->hierarchyFacility('880e8400-e29b-41d4-a716-446655449182', 'floor', $parent->id, false);
    $draft = $this->hierarchyFacility('880e8400-e29b-41d4-a716-446655449183', 'zone', self::FACILITY_ID, true);
    $this->hierarchyChange('880e8400-e29b-41d4-a716-446655449184', $parent->id, ['status' => 'archived']);
    $this->entityManager->flush();
    $this->adapter->createOrGetPending(self::PUBLICATION_ID, self::INTERVENTION_ID, 1);
    $this->adapter->markProcessing(self::PUBLICATION_ID);
    $connection = $this->entityManager->getConnection();

    try {
      $this->adapter->publish(self::PUBLICATION_ID);
      self::fail('Retained active dependents must still block the final archive.');
    } catch (\Facility\Domain\Exception\FacilityHasActiveDependentsException) {
      self::assertSame('active', $connection->fetchOne('SELECT status FROM facilities WHERE id = ?', [$parent->id]));
      self::assertSame(1, $connection->fetchOne('SELECT revision FROM facilities WHERE id = ?', [$parent->id]));
      self::assertSame($parent->id, $connection->fetchOne('SELECT parent_facility_id FROM facilities WHERE id = ?', [$child->id]));
      self::assertSame('draft', $connection->fetchOne('SELECT record_status FROM facilities WHERE id = ?', [$draft->id]));
      self::assertSame('proposed', $connection->fetchOne('SELECT status FROM intervention_changes WHERE id = ?', ['880e8400-e29b-41d4-a716-446655449184']));
      self::assertSame('submitted', $connection->fetchOne('SELECT status FROM interventions WHERE id = ?', [self::INTERVENTION_ID]));
    }
  }

  #[Test]
  public function testPublicationRestoresTheChildBeforeItsParentsLaterRestore(): void
  {
    $parent = $this->hierarchyFacility('880e8400-e29b-41d4-a716-446655449191', 'building', self::FACILITY_ID, false);
    $parent->status = 'archived';
    $child = $this->hierarchyFacility('880e8400-e29b-41d4-a716-446655449192', 'floor', $parent->id, false);
    $child->status = 'archived';
    $this->hierarchyChange('880e8400-e29b-41d4-a716-446655449193', $child->id, ['status' => 'active']);
    $this->hierarchyChange('880e8400-e29b-41d4-a716-446655449194', $parent->id, ['status' => 'active']);
    $this->entityManager->flush();
    $this->adapter->createOrGetPending(self::PUBLICATION_ID, self::INTERVENTION_ID, 1);
    $this->adapter->markProcessing(self::PUBLICATION_ID);
    self::assertTrue($this->adapter->publish(self::PUBLICATION_ID));
    $connection = $this->entityManager->getConnection();
    self::assertSame('active', $connection->fetchOne('SELECT status FROM facilities WHERE id = ?', [$parent->id]));
    self::assertSame('active', $connection->fetchOne('SELECT status FROM facilities WHERE id = ?', [$child->id]));
    self::assertSame(2, $connection->fetchOne('SELECT revision FROM facilities WHERE id = ?', [$parent->id]));
    self::assertSame(2, $connection->fetchOne('SELECT revision FROM facilities WHERE id = ?', [$child->id]));
  }

  /**
   * Method testPublicationArchivesBothParentAndChildWithoutChangingTheirRelationship.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testPublicationArchivesBothParentAndChildWithoutChangingTheirRelationship(): void
  {
    $parent = $this->hierarchyFacility('880e8400-e29b-41d4-a716-446655449201', 'building', self::FACILITY_ID, false);
    $child = $this->hierarchyFacility('880e8400-e29b-41d4-a716-446655449202', 'floor', $parent->id, false);
    $this->hierarchyChange('880e8400-e29b-41d4-a716-446655449203', $parent->id, ['status' => 'archived']);
    $this->hierarchyChange('880e8400-e29b-41d4-a716-446655449204', $child->id, ['status' => 'archived']);
    $this->entityManager->flush();
    $this->adapter->createOrGetPending(self::PUBLICATION_ID, self::INTERVENTION_ID, 1);
    $this->adapter->markProcessing(self::PUBLICATION_ID);

    self::assertTrue($this->adapter->publish(self::PUBLICATION_ID));
    $connection = $this->entityManager->getConnection();
    self::assertSame('archived', $connection->fetchOne('SELECT status FROM facilities WHERE id = ?', [$parent->id]));
    self::assertSame('archived', $connection->fetchOne('SELECT status FROM facilities WHERE id = ?', [$child->id]));
    self::assertSame($parent->id, $connection->fetchOne('SELECT parent_facility_id FROM facilities WHERE id = ?', [$child->id]));
    self::assertSame(2, $connection->fetchOne('SELECT revision FROM facilities WHERE id = ?', [$parent->id]));
    self::assertSame(2, $connection->fetchOne('SELECT revision FROM facilities WHERE id = ?', [$child->id]));
  }

  /**
   * Method testPublicationRetainsLegacyRelationshipsWhenDescriptiveChangesRepeatHierarchyFields.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testPublicationRetainsLegacyRelationshipsWhenDescriptiveChangesRepeatHierarchyFields(): void
  {
    $legacy = $this->hierarchyFacility('880e8400-e29b-41d4-a716-446655449211', 'building', self::FACILITY_ID, false);
    $legacy->parentFacility = null;
    $this->hierarchyChange('880e8400-e29b-41d4-a716-446655449212', $legacy->id, ['name' => 'Updated legacy building', 'type' => 'building', 'parent' => null]);
    $this->entityManager->flush();
    $this->adapter->createOrGetPending(self::PUBLICATION_ID, self::INTERVENTION_ID, 1);
    $this->adapter->markProcessing(self::PUBLICATION_ID);

    self::assertTrue($this->adapter->publish(self::PUBLICATION_ID));
    $connection = $this->entityManager->getConnection();
    self::assertSame('Updated legacy building', $connection->fetchOne('SELECT name FROM facilities WHERE id = ?', [$legacy->id]));
    self::assertSame('building', $connection->fetchOne('SELECT type FROM facilities WHERE id = ?', [$legacy->id]));
    self::assertNull($connection->fetchOne('SELECT parent_facility_id FROM facilities WHERE id = ?', [$legacy->id]));
    self::assertSame(2, $connection->fetchOne('SELECT revision FROM facilities WHERE id = ?', [$legacy->id]));
  }

  /**
   * Method testArchivalDoesNotPermitANewRelationshipToAnArchivedParent.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testArchivalDoesNotPermitANewRelationshipToAnArchivedParent(): void
  {
    $destination = $this->hierarchyFacility('880e8400-e29b-41d4-a716-446655449221', 'zone', self::FACILITY_ID, false);
    $zone = $this->hierarchyFacility('880e8400-e29b-41d4-a716-446655449222', 'zone', self::FACILITY_ID, false);
    $this->hierarchyChange('880e8400-e29b-41d4-a716-446655449223', $destination->id, ['status' => 'archived']);
    $this->hierarchyChange('880e8400-e29b-41d4-a716-446655449224', $zone->id, ['status' => 'archived', 'parent' => '/api/facilities/' . $destination->id]);
    $this->entityManager->flush();
    $this->adapter->createOrGetPending(self::PUBLICATION_ID, self::INTERVENTION_ID, 1);
    $connection = $this->entityManager->getConnection();

    try {
      $this->adapter->publish(self::PUBLICATION_ID);
      self::fail('Archival cannot bypass validation of a newly assigned parent.');
    } catch (\Facility\Domain\Exception\FacilityHierarchyException $exception) {
      self::assertSame('The parent facility must be active.', $exception->getMessage());
      self::assertSame('active', $connection->fetchOne('SELECT status FROM facilities WHERE id = ?', [$destination->id]));
      self::assertSame('active', $connection->fetchOne('SELECT status FROM facilities WHERE id = ?', [$zone->id]));
      self::assertSame(self::FACILITY_ID, $connection->fetchOne('SELECT parent_facility_id FROM facilities WHERE id = ?', [$zone->id]));
      self::assertSame('submitted', $connection->fetchOne('SELECT status FROM interventions WHERE id = ?', [self::INTERVENTION_ID]));
    }
  }

  #[Test]
  public function testCompletedPublicationQueuesOneEventAndAuditReplayIsIdempotent(): void
  {
    $this->adapter->createOrGetPending(self::PUBLICATION_ID, self::INTERVENTION_ID, 1);
    $container = self::getContainer();
    $transport = $container->get('messenger.transport.main_outbox');
    self::assertInstanceOf(InMemoryTransport::class, $transport);
    $transport->reset();
    $events = $container->get(TransactionalEventDispatcher::class);
    self::assertInstanceOf(EventDispatcherPort::class, $events);
    $handler = $this->executionHandler($events);
    $handler(new ExecutePublicationCommand(self::PUBLICATION_ID));
    $handler(new ExecutePublicationCommand(self::PUBLICATION_ID));
    self::assertCount(1, $transport->getSent());
    $message = $transport->getSent()[0]->getMessage();
    self::assertInstanceOf(OutboxEvent::class, $message);
    self::assertInstanceOf(InterventionPublishedEvent::class, $message->event);
    self::assertSame('Publication Adapter Intervention', $message->event->interventionName);
    $auth = $container->get('doctrine.dbal.auth_connection');
    self::assertInstanceOf(\Doctrine\DBAL\Connection::class, $auth);
    $count = fn (): mixed => $auth->fetchOne("SELECT COUNT(*) FROM audit_events WHERE action = 'intervention.published' AND subject_id = ?", [self::INTERVENTION_ID]);
    self::assertSame(0, $count());
    $delivery = $container->get(DeliverOutboxEventHandler::class);
    self::assertInstanceOf(DeliverOutboxEventHandler::class, $delivery);
    $delivery($message);
    $delivery($message);
    self::assertSame(1, $count());
  }

  #[Test]
  public function testAnOutboxWriteFailureRollsBackPublicationBeforeRecordingTheFailure(): void
  {
    $this->adapter->createOrGetPending(self::PUBLICATION_ID, self::INTERVENTION_ID, 1);
    $events = $this->createMock(EventDispatcherPort::class);
    $events->expects(self::exactly(2))->method('dispatch')->willReturnCallback(function (object $event): void {
      if ($event instanceof InterventionPublishedEvent) {
        throw new RuntimeException('Outbox unavailable');
      }
      self::assertInstanceOf(InterventionPublicationFailedEvent::class, $event);
      self::assertSame('submitted', $this->entityManager->getConnection()->fetchOne('SELECT status FROM interventions WHERE id = ?', [self::INTERVENTION_ID]));
    });
    $this->executionHandler($events)(new ExecutePublicationCommand(self::PUBLICATION_ID));
    self::assertSame('failed', $this->adapter->find(self::PUBLICATION_ID)?->status);
    self::assertSame(1, $this->entityManager->getConnection()->fetchOne('SELECT revision FROM interventions WHERE id = ?', [self::INTERVENTION_ID]));
  }

  #[Test]
  public function testInterventionContextExposesTheOrganizationStatusAndRevision(): void
  {
    $context = $this->adapter->interventionContext(self::INTERVENTION_ID);

    self::assertNotNull($context);
    self::assertSame(self::INTERVENTION_ID, $context->interventionId);
    self::assertSame(self::ORGANIZATION_ID, $context->organizationId);
    self::assertSame('submitted', $context->status);
    self::assertSame(1, $context->revision);
  }

  #[Test]
  public function testInterventionContextReturnsNullForAnUnknownIntervention(): void
  {
    self::assertNull($this->adapter->interventionContext(self::MISSING_ID));
  }

  #[Test]
  public function testCreateOrGetPendingCreatesOncePerInterventionRevisionThenReturnsTheSameRow(): void
  {
    $created = $this->adapter->createOrGetPending(self::PUBLICATION_ID, self::INTERVENTION_ID, 1);

    self::assertSame(self::PUBLICATION_ID, $created->id);
    self::assertSame(self::INTERVENTION_ID, $created->interventionId);
    self::assertSame(1, $created->interventionRevision);
    self::assertSame('pending', $created->status);
    self::assertNull($created->error);
    self::assertNull($created->completedAt);

    // Same intervention revision: the unique constraint is honored by
    // returning the existing row instead of inserting a second one.
    $again = $this->adapter->createOrGetPending(self::MISSING_ID, self::INTERVENTION_ID, 1);
    self::assertSame(self::PUBLICATION_ID, $again->id);
    self::assertSame(1, $this->entityManager->getRepository(PublicationRecord::class)->count([
      'intervention' => $this->entityManager->getReference(InterventionRecord::class, self::INTERVENTION_ID),
    ]));
  }

  #[Test]
  public function testCreateOrGetPendingRejectsAnUnknownIntervention(): void
  {
    $this->expectException(InterventionNotFoundException::class);

    $this->adapter->createOrGetPending(self::PUBLICATION_ID, self::MISSING_ID, 1);
  }

  #[Test]
  public function testFindReturnsTheViewForAKnownPublicationAndNullOtherwise(): void
  {
    $this->adapter->createOrGetPending(self::PUBLICATION_ID, self::INTERVENTION_ID, 1);

    $view = $this->adapter->find(self::PUBLICATION_ID);
    self::assertNotNull($view);
    self::assertSame(self::PUBLICATION_ID, $view->id);
    self::assertSame('pending', $view->status);

    self::assertNull($this->adapter->find(self::MISSING_ID));
  }

  #[Test]
  public function testFindByInterventionRevisionMatchesOnlyTheStoredRevision(): void
  {
    $this->adapter->createOrGetPending(self::PUBLICATION_ID, self::INTERVENTION_ID, 1);

    $view = $this->adapter->findByInterventionRevision(self::INTERVENTION_ID, 1);
    self::assertNotNull($view);
    self::assertSame(self::PUBLICATION_ID, $view->id);

    self::assertNull($this->adapter->findByInterventionRevision(self::INTERVENTION_ID, 2));
  }

  #[Test]
  public function testMarkProcessingAdvancesPendingButNeverATerminalPublication(): void
  {
    $this->adapter->createOrGetPending(self::PUBLICATION_ID, self::INTERVENTION_ID, 1);

    $this->adapter->markProcessing(self::PUBLICATION_ID);
    self::assertSame('processing', $this->adapter->find(self::PUBLICATION_ID)?->status);

    // Unknown ids are a no-op rather than an error: the queue may retry a
    // publication whose row was already removed.
    $this->adapter->markProcessing(self::MISSING_ID);

    $this->setPublicationStatus('completed');
    $this->adapter->markProcessing(self::PUBLICATION_ID);
    self::assertSame('completed', $this->adapter->find(self::PUBLICATION_ID)?->status);

    $this->setPublicationStatus('failed');
    $this->adapter->markProcessing(self::PUBLICATION_ID);
    self::assertSame('failed', $this->adapter->find(self::PUBLICATION_ID)?->status);
  }

  #[Test]
  public function testRetryFailedResetsOnlyFailedPublications(): void
  {
    $this->adapter->createOrGetPending(self::PUBLICATION_ID, self::INTERVENTION_ID, 1);
    $this->adapter->markFailed(self::PUBLICATION_ID, 'boom');

    $retried = $this->adapter->retryFailed(self::PUBLICATION_ID);
    self::assertSame('pending', $retried->status);
    self::assertNull($retried->error);
    self::assertNull($retried->completedAt);

    // Already pending: retrying is a read, not a second transition.
    $unchanged = $this->adapter->retryFailed(self::PUBLICATION_ID);
    self::assertSame('pending', $unchanged->status);
  }

  #[Test]
  public function testRetryFailedRejectsAnUnknownPublication(): void
  {
    $this->expectException(PublicationNotFoundException::class);

    $this->adapter->retryFailed(self::MISSING_ID);
  }

  #[Test]
  public function testMarkFailedIsIdempotentAndReportsWhetherItTransitioned(): void
  {
    $this->adapter->createOrGetPending(self::PUBLICATION_ID, self::INTERVENTION_ID, 1);

    self::assertTrue($this->adapter->markFailed(self::PUBLICATION_ID, 'first failure'));
    $failed = $this->adapter->find(self::PUBLICATION_ID);
    self::assertSame('failed', $failed?->status);
    self::assertSame('first failure', $failed->error);
    self::assertNotNull($failed->completedAt);

    // Terminal already: the first error message is preserved.
    self::assertFalse($this->adapter->markFailed(self::PUBLICATION_ID, 'second failure'));
    self::assertSame('first failure', $this->adapter->find(self::PUBLICATION_ID)?->error);

    self::assertFalse($this->adapter->markFailed(self::MISSING_ID, 'nothing to fail'));
  }

  #[Test]
  public function testMarkFailedFallsBackToRawSqlWhenTheEntityManagerIsClosed(): void
  {
    $this->adapter->createOrGetPending(self::PUBLICATION_ID, self::INTERVENTION_ID, 1);
    $connection = $this->entityManager->getConnection();

    // A failing publish closes the entity manager, which is exactly when the
    // worker still has to record the failure — hence the DBAL fallback.
    $this->entityManager->close();

    self::assertTrue($this->adapter->markFailed(self::PUBLICATION_ID, 'closed-manager failure'));
    self::assertSame('failed', $connection->fetchOne(
      'SELECT status FROM intervention_publications WHERE id = ?',
      [self::PUBLICATION_ID],
    ));
    self::assertSame('closed-manager failure', $connection->fetchOne(
      'SELECT error FROM intervention_publications WHERE id = ?',
      [self::PUBLICATION_ID],
    ));

    // Terminal already: the guarded UPDATE affects no row.
    self::assertFalse($this->adapter->markFailed(self::PUBLICATION_ID, 'again'));
  }

  #[Test]
  public function testPublishAppliesProposedChangesAndTransitionsTheIntervention(): void
  {
    $this->seedProposedFacilityRenameChange();
    $this->adapter->createOrGetPending(self::PUBLICATION_ID, self::INTERVENTION_ID, 1);
    // The transition table requires `pending` to pass through `processing`
    // before reaching `completed` — mirroring the real dispatch path, where
    // ExecutePublicationHandler always calls markProcessing() before publish().
    $this->adapter->markProcessing(self::PUBLICATION_ID);

    self::assertTrue($this->adapter->publish(self::PUBLICATION_ID));

    $this->entityManager->clear();

    $intervention = $this->entityManager->find(InterventionRecord::class, self::INTERVENTION_ID);
    self::assertInstanceOf(InterventionRecord::class, $intervention);
    self::assertSame('published', $intervention->status);
    self::assertSame(2, $intervention->revision);

    $change = $this->entityManager->find(InterventionChangeRecord::class, self::CHANGE_ID);
    self::assertInstanceOf(InterventionChangeRecord::class, $change);
    self::assertSame('applied', $change->status);
    self::assertSame(2, $change->revision);

    $facility = $this->entityManager->find(FacilityRecord::class, self::FACILITY_ID);
    self::assertInstanceOf(FacilityRecord::class, $facility);
    self::assertSame('Renamed by publication', $facility->name);

    $publication = $this->adapter->find(self::PUBLICATION_ID);
    self::assertSame('completed', $publication?->status);
    self::assertNotNull($publication->completedAt);
  }

  #[Test]
  public function testPublishIsIdempotentOnAnAlreadyCompletedPublication(): void
  {
    $this->adapter->createOrGetPending(self::PUBLICATION_ID, self::INTERVENTION_ID, 1);
    $this->setPublicationStatus('completed');

    // At-least-once delivery: a replay must not re-transition or re-notify.
    self::assertFalse($this->adapter->publish(self::PUBLICATION_ID));

    $this->entityManager->clear();
    $intervention = $this->entityManager->find(InterventionRecord::class, self::INTERVENTION_ID);
    self::assertInstanceOf(InterventionRecord::class, $intervention);
    self::assertSame('submitted', $intervention->status);
    self::assertSame(1, $intervention->revision);
  }

  #[Test]
  public function testPublishRejectsAnUnknownPublication(): void
  {
    $this->expectException(PublicationNotFoundException::class);

    $this->adapter->publish(self::MISSING_ID);
  }

  #[Test]
  public function testPublishConflictsWhenTheInterventionMovedOnAfterTheJobWasQueued(): void
  {
    $this->adapter->createOrGetPending(self::PUBLICATION_ID, self::INTERVENTION_ID, 1);

    $intervention = $this->entityManager->find(InterventionRecord::class, self::INTERVENTION_ID);
    self::assertInstanceOf(InterventionRecord::class, $intervention);
    $intervention->status = 'draft';
    $this->entityManager->flush();
    $this->entityManager->clear();

    $this->expectException(InterventionConflictException::class);

    $this->adapter->publish(self::PUBLICATION_ID);
  }

  #[Test]
  public function testPublishConflictsWhenTheInterventionRevisionMovedOn(): void
  {
    $this->adapter->createOrGetPending(self::PUBLICATION_ID, self::INTERVENTION_ID, 1);

    $intervention = $this->entityManager->find(InterventionRecord::class, self::INTERVENTION_ID);
    self::assertInstanceOf(InterventionRecord::class, $intervention);
    $intervention->revision = 7;
    $this->entityManager->flush();
    $this->entityManager->clear();

    $this->expectException(InterventionConflictException::class);

    $this->adapter->publish(self::PUBLICATION_ID);
  }

  #[Test]
  public function testFindRejectsAPublicationWhoseInterventionIsGone(): void
  {
    $this->relaxPublicationInterventionConstraint();
    $this->insertOrphanPublication();

    $this->expectException(PublicationNotFoundException::class);

    $this->adapter->find(self::ORPHAN_PUBLICATION_ID);
  }

  #[Test]
  public function testPublishRejectsAPublicationWhoseInterventionIsGone(): void
  {
    $this->relaxPublicationInterventionConstraint();
    $this->insertOrphanPublication();

    $this->expectException(PublicationNotFoundException::class);

    $this->adapter->publish(self::ORPHAN_PUBLICATION_ID);
  }

  #[Test]
  public function testPublishRejectsAnInterventionWithoutAnOrganization(): void
  {
    $this->adapter->createOrGetPending(self::PUBLICATION_ID, self::INTERVENTION_ID, 1);
    $this->entityManager->clear();

    // The mapping forbids this, so the column constraint has to be relaxed to
    // reach the guard. The DDL is transactional in PostgreSQL and is rolled
    // back with the rest of the test.
    $this->entityManager->getConnection()->executeStatement(
      'ALTER TABLE interventions ALTER COLUMN organization_id DROP NOT NULL',
    );
    $this->entityManager->getConnection()->executeStatement(
      'UPDATE interventions SET organization_id = NULL WHERE id = ?',
      [self::INTERVENTION_ID],
    );

    $this->expectException(InterventionConflictException::class);
    $this->expectExceptionMessage('Intervention organization is unavailable.');

    $this->adapter->publish(self::PUBLICATION_ID);
  }

  /**
   * Builds a hierarchy row for publication invariants, without implicit repair.
   *
   * @param string $id the fixture identifier
   * @param string $type the facility type
   * @param string $parentId its existing parent
   * @param bool $draft whether it belongs to this intervention's publication
   */
  private function hierarchyFacility(string $id, string $type, string $parentId, bool $draft): FacilityRecord
  {
    $record = new FacilityRecord();
    $record->id = $id;
    $record->organization = $this->entityManager->find(OrganizationRecord::class, self::ORGANIZATION_ID);
    $record->parentFacility = $this->entityManager->find(FacilityRecord::class, $parentId);
    $record->name = 'Publication ' . $type;
    $record->type = $type;
    $record->recordStatus = $draft ? 'draft' : 'published';
    $record->interventionId = $draft ? self::INTERVENTION_ID : null;
    $record->createdAt = new DateTimeImmutable();
    $record->updatedAt = $record->createdAt;
    $this->entityManager->persist($record);

    return $record;
  }

  /**
   * @param string $id the proposed change identifier
   * @param string $facilityId the target facility
   * @param array<string, mixed> $patch the proposed final changes
   */
  private function hierarchyChange(string $id, string $facilityId, array $patch): void
  {
    $change = new InterventionChangeRecord();
    $change->id = $id;
    $change->intervention = $this->entityManager->find(InterventionRecord::class, self::INTERVENTION_ID);
    $change->resource = '/api/facilities/' . $facilityId;
    $change->patch = $patch;
    $change->createdAt = new DateTimeImmutable();
    $change->updatedAt = $change->createdAt;
    $this->entityManager->persist($change);
  }

  private function executionHandler(EventDispatcherPort $events): ExecutePublicationHandler
  {
    $resources = $this->createStub(InterventionResourceGatewayPort::class);
    $resources->method('summary')->willReturn(new InterventionResourceSummary(1, 0, 0));
    $resources->method('equipmentDrafts')->willReturn([]);
    $resources->method('workItemSummary')->willReturn(new InterventionWorkItemSummary(0, 0, 0, 0));

    return new ExecutePublicationHandler(
      $this->adapter,
      new InterventionIssueFinder($resources, $this->createStub(InterventionAttachmentRepositoryPort::class)),
      $events,
      new DbalTransactionManagerAdapter($this->entityManager->getConnection()),
    );
  }

  /**
   * Drops the guarantees the mapping relies on — the NOT NULL and the foreign
   * key on `intervention_publications.intervention_id` — so the adapter's
   * defensive branches become reachable. PostgreSQL DDL is transactional, so
   * DAMA rolls this back with the test.
   */
  private function relaxPublicationInterventionConstraint(): void
  {
    $connection = $this->entityManager->getConnection();
    $connection->executeStatement('ALTER TABLE intervention_publications ALTER COLUMN intervention_id DROP NOT NULL');

    /** @var list<string> $constraints */
    $constraints = $connection->fetchFirstColumn(
      "SELECT conname FROM pg_constraint WHERE conrelid = 'intervention_publications'::regclass AND contype = 'f'",
    );
    foreach ($constraints as $constraint) {
      $connection->executeStatement(sprintf('ALTER TABLE intervention_publications DROP CONSTRAINT %s', $constraint));
    }
  }

  private function insertOrphanPublication(): void
  {
    $this->entityManager->getConnection()->executeStatement(
      'INSERT INTO intervention_publications (id, intervention_id, intervention_revision, status, created_at) VALUES (?, NULL, 1, ?, ?)',
      [self::ORPHAN_PUBLICATION_ID, 'pending', '2026-05-04T10:00:00'],
    );
    $this->entityManager->clear();
  }

  /**
   * @param string $id the retained inspection identity
   * @param string $equipmentId its referenced equipment
   * @param OrganizationRecord $organization the owning organization
   *
   * @return \Inspection\Infrastructure\Persistence\Doctrine\Record\InspectionRecord the persisted inspection
   */
  private function retainedInspection(string $id, string $equipmentId, OrganizationRecord $organization): \Inspection\Infrastructure\Persistence\Doctrine\Record\InspectionRecord
  {
    $inspection = new \Inspection\Infrastructure\Persistence\Doctrine\Record\InspectionRecord();
    $inspection->id = $id;
    $inspection->organization = $organization;
    $inspection->equipmentId = $equipmentId;
    $inspection->inspectorType = 'user';
    $inspection->inspectorName = 'Draft dependency inspection';
    $inspection->result = 'compliant';
    $inspection->status = 'closed';
    $inspection->performedAt = new DateTimeImmutable();
    $inspection->createdAt = $inspection->performedAt;
    $inspection->updatedAt = $inspection->performedAt;
    $this->entityManager->persist($inspection);

    return $inspection;
  }

  private function seedOrganizationAndIntervention(): void
  {
    $createdAt = new DateTimeImmutable('2026-05-04T08:00:00+00:00');

    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $organization->name = 'Publication Adapter Test';
    $organization->slug = 'publication-adapter-test';
    $organization->ownerUserId = self::OWNER_USER_ID;
    $organization->createdByUserId = self::OWNER_USER_ID;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = $createdAt;
    $organization->updatedAt = $createdAt;
    $this->entityManager->persist($organization);

    $facility = new FacilityRecord();
    $facility->id = self::FACILITY_ID;
    $facility->organization = $organization;
    $facility->type = 'site';
    $facility->name = 'Publication Adapter Site';
    $facility->status = 'active';
    $facility->recordStatus = 'published';
    $facility->createdAt = $createdAt;
    $facility->updatedAt = $createdAt;
    $this->entityManager->persist($facility);

    $intervention = new InterventionRecord();
    $intervention->id = self::INTERVENTION_ID;
    $intervention->organization = $organization;
    $intervention->type = 'site_setup';
    $intervention->name = 'Publication Adapter Intervention';
    $intervention->number = 9001;
    $intervention->status = 'submitted';
    $intervention->priority = 'normal';
    $intervention->revision = 1;
    $intervention->responsibleId = self::RESPONSIBLE_MEMBER_ID;
    $intervention->participants = [self::RESPONSIBLE_MEMBER_ID];
    $intervention->createdAt = $createdAt;
    $intervention->updatedAt = $createdAt;
    $this->entityManager->persist($intervention);

    $this->entityManager->flush();
  }

  private function seedProposedFacilityRenameChange(): void
  {
    /** @var InterventionRecord $intervention */
    $intervention = $this->entityManager->getReference(InterventionRecord::class, self::INTERVENTION_ID);

    $change = new InterventionChangeRecord();
    $change->id = self::CHANGE_ID;
    $change->intervention = $intervention;
    $change->resource = sprintf('/api/facilities/%s', self::FACILITY_ID);
    $change->patch = ['name' => 'Renamed by publication'];
    $change->status = 'proposed';
    $change->revision = 1;
    $change->createdAt = new DateTimeImmutable('2026-05-04T09:00:00+00:00');
    $change->updatedAt = $change->createdAt;
    $this->entityManager->persist($change);
    $this->entityManager->flush();
  }

  private function setPublicationStatus(string $status): void
  {
    $publication = $this->entityManager->find(PublicationRecord::class, self::PUBLICATION_ID);
    self::assertInstanceOf(PublicationRecord::class, $publication);
    $publication->status = $status;
    $this->entityManager->flush();
  }
}
