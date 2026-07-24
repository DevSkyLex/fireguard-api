<?php

declare(strict_types=1);

namespace Tests\Integration\Equipment\Infrastructure\Adapter\Intervention;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Application\Port\Inbound\EquipmentMaintenanceLogSynchronizerPort;
use Equipment\Application\Port\Outbound\FacilityValidationPort;
use Equipment\Infrastructure\Adapter\Intervention\EquipmentInterventionResourceAdapter;
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Intervention\Application\Contract\Resource\{InterventionEquipmentDraft, InterventionResourceAssignment};
use Intervention\Domain\Exception\{InterventionConflictException, InterventionResourceNotFoundException};
use Intervention\Domain\ValueObject\InterventionResourceType;
use InvalidArgumentException;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{AllowMockObjectsWithoutExpectations, CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test EquipmentInterventionResourceAdapter.
 *
 * @category Adapter Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(EquipmentInterventionResourceAdapter::class)]
final class EquipmentInterventionResourceAdapterTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = 'e91d8400-e29b-41d4-a716-4466551c0001';

  private const string OTHER_ORGANIZATION_ID = 'e91d8400-e29b-41d4-a716-4466551c0002';

  private const string FACILITY_ID = 'e91d8400-e29b-41d4-a716-4466551c00f0';

  private const string FACILITY_IRI = '/api/facilities/' . self::FACILITY_ID;

  private const string INTERVENTION_A = 'e91d8400-e29b-41d4-a716-4466551c0a01';

  private const string INTERVENTION_B = 'e91d8400-e29b-41d4-a716-4466551c0a02';

  private const string INTERVENTION_PUBLISH = 'e91d8400-e29b-41d4-a716-4466551c0a03';

  private const string INTERVENTION_DISCARD = 'e91d8400-e29b-41d4-a716-4466551c0a04';

  private const string INTERVENTION_DRAFTS = 'e91d8400-e29b-41d4-a716-4466551c0a05';

  private EntityManagerInterface $entityManager;

  private FacilityValidationPort&MockObject $facilityValidation;

  private EquipmentMaintenanceLogSynchronizerPort&MockObject $maintenanceSynchronizer;

  private EquipmentInterventionResourceAdapter $adapter;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();

    // Both collaborators are command-style ports invoked only by apply()/publishDrafts();
    // mocking them keeps the real FacilityValidationAdapter and maintenance-log writer
    // out of the picture so each branch is exercised deterministically against the DB.
    $this->facilityValidation = $this->createMock(FacilityValidationPort::class);
    $this->maintenanceSynchronizer = $this->createMock(EquipmentMaintenanceLogSynchronizerPort::class);
    $this->adapter = new EquipmentInterventionResourceAdapter(
      $this->entityManager,
      $this->facilityValidation,
      $this->maintenanceSynchronizer,
    );

    $this->createOrganization();
    $this->entityManager->flush();
    $this->entityManager->clear();
  }

  protected function tearDown(): void
  {
    $this->cleanup();
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  #[AllowMockObjectsWithoutExpectations]
  public function testSupportsMatchesEquipmentResourceIris(): void
  {
    self::assertTrue($this->adapter->supports('/api/equipment/e91d8400-e29b-41d4-a716-4466551c0010'));
    self::assertFalse($this->adapter->supports('/api/facilities/e91d8400-e29b-41d4-a716-4466551c0010'));
    self::assertFalse($this->adapter->supports('/api/equipment/e91d8400-e29b-41d4-a716-4466551c0010/media'));
  }

  #[Test]
  #[AllowMockObjectsWithoutExpectations]
  public function testSupportsResourceTypeOnlyAcceptsEquipment(): void
  {
    self::assertTrue($this->adapter->supportsResourceType(InterventionResourceType::EQUIPMENT));
    self::assertFalse($this->adapter->supportsResourceType(InterventionResourceType::FACILITY));
    self::assertFalse($this->adapter->supportsResourceType(InterventionResourceType::INSPECTION));
  }

  #[Test]
  #[AllowMockObjectsWithoutExpectations]
  public function testReadOnlyLookupsReflectPersistedState(): void
  {
    $equipmentId = 'e91d8400-e29b-41d4-a716-4466551c0110';
    $clientId = 'e91d8400-e29b-41d4-a716-4466551c0111';
    $this->persistEquipment($equipmentId, clientId: $clientId, serialNumber: 'SN-LOOKUP');
    $this->entityManager->flush();
    $this->entityManager->clear();

    self::assertTrue($this->adapter->resourceExists($equipmentId));
    self::assertFalse($this->adapter->resourceExists('e91d8400-e29b-41d4-a716-4466551c0199'));

    self::assertTrue($this->adapter->resourceBelongsToOrganization($equipmentId, self::ORGANIZATION_ID));
    self::assertFalse($this->adapter->resourceBelongsToOrganization($equipmentId, self::OTHER_ORGANIZATION_ID));
    self::assertFalse($this->adapter->resourceBelongsToOrganization('e91d8400-e29b-41d4-a716-4466551c0199', self::ORGANIZATION_ID));

    self::assertTrue($this->adapter->clientIdExists($clientId));
    self::assertFalse($this->adapter->clientIdExists('e91d8400-e29b-41d4-a716-4466551c0199'));
  }

  #[Test]
  #[AllowMockObjectsWithoutExpectations]
  public function testAssignMarksRecordAsDraft(): void
  {
    $equipmentId = 'e91d8400-e29b-41d4-a716-4466551c0210';
    $interventionId = 'e91d8400-e29b-41d4-a716-4466551c0211';
    $clientId = 'e91d8400-e29b-41d4-a716-4466551c0212';
    $this->persistEquipment($equipmentId);
    $this->entityManager->flush();
    $this->entityManager->clear();

    $assignment = $this->adapter->assign($equipmentId, $interventionId, $clientId);

    self::assertInstanceOf(InterventionResourceAssignment::class, $assignment);
    self::assertSame($interventionId, $assignment->interventionId);
    self::assertSame('draft', $assignment->recordStatus);
    self::assertSame(1, $assignment->revision);

    $this->entityManager->clear();
    $reloaded = $this->entityManager->find(EquipmentRecord::class, $equipmentId);
    self::assertInstanceOf(EquipmentRecord::class, $reloaded);
    self::assertSame('draft', $reloaded->recordStatus);
    self::assertSame($interventionId, $reloaded->interventionId);
    self::assertSame($clientId, $reloaded->clientId);
  }

  #[Test]
  #[AllowMockObjectsWithoutExpectations]
  public function testAssignWithoutInterventionPublishesRecord(): void
  {
    $equipmentId = 'e91d8400-e29b-41d4-a716-4466551c0220';
    $this->persistEquipment($equipmentId, recordStatus: 'draft');
    $this->entityManager->flush();
    $this->entityManager->clear();

    $assignment = $this->adapter->assign($equipmentId, null, null);

    self::assertNull($assignment->interventionId);
    self::assertSame('published', $assignment->recordStatus);
    self::assertSame(1, $assignment->revision);

    $this->entityManager->clear();
    $reloaded = $this->entityManager->find(EquipmentRecord::class, $equipmentId);
    self::assertInstanceOf(EquipmentRecord::class, $reloaded);
    self::assertSame('published', $reloaded->recordStatus);
    self::assertNull($reloaded->interventionId);
  }

  #[Test]
  #[AllowMockObjectsWithoutExpectations]
  public function testAssignThrowsWhenRecordIsMissing(): void
  {
    $this->expectException(InterventionResourceNotFoundException::class);

    $this->adapter->assign('e91d8400-e29b-41d4-a716-4466551c0299', null, null);
  }

  #[Test]
  #[AllowMockObjectsWithoutExpectations]
  public function testCountsForInterventionsAggregateAndScopeBlockers(): void
  {
    // Two for A (one with a facility, one without), one for B (without a facility).
    $this->persistEquipment('e91d8400-e29b-41d4-a716-4466551c0310', interventionId: self::INTERVENTION_A, facilityId: self::FACILITY_ID);
    $this->persistEquipment('e91d8400-e29b-41d4-a716-4466551c0311', interventionId: self::INTERVENTION_A);
    $this->persistEquipment('e91d8400-e29b-41d4-a716-4466551c0312', interventionId: self::INTERVENTION_B);
    $this->entityManager->flush();
    $this->entityManager->clear();

    self::assertSame(2, $this->adapter->countForIntervention(self::INTERVENTION_A));
    self::assertSame(1, $this->adapter->countForIntervention(self::INTERVENTION_B));

    $counts = $this->adapter->countsForInterventions([self::INTERVENTION_A, self::INTERVENTION_B]);
    self::assertSame(2, $counts[self::INTERVENTION_A] ?? null);
    self::assertSame(1, $counts[self::INTERVENTION_B] ?? null);
    self::assertSame([], $this->adapter->countsForInterventions([]));

    // Blockers count only facility-less equipment: A drops to one, B keeps its one.
    $blockers = $this->adapter->blockerCountsForInterventions([self::INTERVENTION_A, self::INTERVENTION_B]);
    self::assertSame(1, $blockers[self::INTERVENTION_A] ?? null);
    self::assertSame(1, $blockers[self::INTERVENTION_B] ?? null);
    self::assertSame([], $this->adapter->blockerCountsForInterventions([]));
  }

  #[Test]
  #[AllowMockObjectsWithoutExpectations]
  public function testEquipmentDraftsMapsRecordsToDrafts(): void
  {
    $withFacility = 'e91d8400-e29b-41d4-a716-4466551c0410';
    $withoutFacility = 'e91d8400-e29b-41d4-a716-4466551c0411';
    $this->persistEquipment($withFacility, recordStatus: 'draft', interventionId: self::INTERVENTION_DRAFTS, facilityId: self::FACILITY_ID, serialNumber: 'SN-DRAFT-1');
    $this->persistEquipment($withoutFacility, recordStatus: 'draft', interventionId: self::INTERVENTION_DRAFTS);
    $this->entityManager->flush();
    $this->entityManager->clear();

    $drafts = $this->adapter->equipmentDrafts(self::INTERVENTION_DRAFTS);
    self::assertCount(2, $drafts);

    /** @var array<string, InterventionEquipmentDraft> $byId */
    $byId = [];
    foreach ($drafts as $draft) {
      self::assertInstanceOf(InterventionEquipmentDraft::class, $draft);
      $byId[$draft->id] = $draft;
    }

    $first = $byId[$withFacility] ?? null;
    self::assertInstanceOf(InterventionEquipmentDraft::class, $first);
    self::assertSame(self::FACILITY_ID, $first->facilityId);
    self::assertSame('SN-DRAFT-1', $first->serialNumber);

    $second = $byId[$withoutFacility] ?? null;
    self::assertInstanceOf(InterventionEquipmentDraft::class, $second);
    self::assertNull($second->facilityId);
    self::assertNull($second->serialNumber);

    // A different intervention shares nothing.
    self::assertSame([], $this->adapter->equipmentDrafts(self::INTERVENTION_B));
  }

  #[Test]
  #[AllowMockObjectsWithoutExpectations]
  public function testApplyRejectsUnknownPatchFields(): void
  {
    $this->assertApplyThrows(
      self::ORGANIZATION_ID,
      '/api/equipment/e91d8400-e29b-41d4-a716-4466551c0510',
      ['unknownField' => 'x'],
      'Unsupported equipment patch fields',
    );
  }

  #[Test]
  #[AllowMockObjectsWithoutExpectations]
  public function testApplyRejectsInvalidTargets(): void
  {
    $published = 'e91d8400-e29b-41d4-a716-4466551c0520';
    $draft = 'e91d8400-e29b-41d4-a716-4466551c0521';
    $this->persistEquipment($published);
    $this->persistEquipment($draft, recordStatus: 'draft');
    $this->entityManager->flush();
    $this->entityManager->clear();

    // Missing target.
    $this->assertApplyThrows(
      self::ORGANIZATION_ID,
      '/api/equipment/e91d8400-e29b-41d4-a716-4466551c0599',
      ['brand' => 'x'],
      'Proposed equipment change target is invalid.',
    );
    // Target owned by another organization.
    $this->assertApplyThrows(
      self::OTHER_ORGANIZATION_ID,
      '/api/equipment/' . $published,
      ['brand' => 'x'],
      'Proposed equipment change target is invalid.',
    );
    // Target is a draft, not a published record.
    $this->assertApplyThrows(
      self::ORGANIZATION_ID,
      '/api/equipment/' . $draft,
      ['brand' => 'x'],
      'Proposed equipment change target is invalid.',
    );
  }

  #[Test]
  public function testApplyUpdatesScalarFieldsWithoutStatusTransition(): void
  {
    $this->maintenanceSynchronizer->expects($this->never())->method('syncForStatusTransition');
    $this->facilityValidation->expects($this->never())->method('assertFacilityIsAssignable');

    $equipmentId = 'e91d8400-e29b-41d4-a716-4466551c0530';
    $this->persistEquipment($equipmentId, serialNumber: 'SN-OLD', locationLabel: 'Room 1');
    $this->entityManager->flush();
    $this->entityManager->clear();

    $this->adapter->apply(self::ORGANIZATION_ID, '/api/equipment/' . $equipmentId, [
      'type' => 'sprinkler',
      'subType' => 'wet',
      'brand' => 'ACME',
      'model' => 'X1',
      'serialNumber' => 'SN-NEW',
      'locationLabel' => null,
    ]);
    $this->entityManager->flush();
    $this->entityManager->clear();

    $reloaded = $this->entityManager->find(EquipmentRecord::class, $equipmentId);
    self::assertInstanceOf(EquipmentRecord::class, $reloaded);
    self::assertSame('sprinkler', $reloaded->type);
    self::assertSame('wet', $reloaded->subType);
    self::assertSame('ACME', $reloaded->brand);
    self::assertSame('X1', $reloaded->model);
    self::assertSame('SN-NEW', $reloaded->serialNumber);
    self::assertNull($reloaded->locationLabel);
    self::assertSame(2, $reloaded->revision);
    self::assertGreaterThan(new DateTimeImmutable('2026-01-01T00:00:00+00:00'), $reloaded->updatedAt);
  }

  #[Test]
  public function testApplyRejectsInvalidFieldValues(): void
  {
    $this->facilityValidation->expects($this->never())->method('assertFacilityIsAssignable');
    $this->maintenanceSynchronizer->expects($this->never())->method('syncForStatusTransition');

    $equipmentId = 'e91d8400-e29b-41d4-a716-4466551c0540';
    $iri = '/api/equipment/' . $equipmentId;
    $this->persistEquipment($equipmentId);
    $this->entityManager->flush();
    $this->entityManager->clear();

    $this->assertApplyThrows(self::ORGANIZATION_ID, $iri, ['type' => ''], 'Equipment type cannot be empty.');
    $this->assertApplyThrows(self::ORGANIZATION_ID, $iri, ['brand' => 123], 'must be a string or null');
    $this->assertApplyThrows(self::ORGANIZATION_ID, $iri, ['status' => 'bogus'], 'Proposed equipment status is invalid.');
    $this->assertApplyThrows(self::ORGANIZATION_ID, $iri, ['facility' => 123], 'Proposed equipment facility must be an IRI or null.');
    $this->assertApplyThrows(self::ORGANIZATION_ID, $iri, ['facility' => '/api/wrong/x'], 'Invalid facilities resource IRI.');
  }

  #[Test]
  public function testApplyAssignsFacilityViaValidationPort(): void
  {
    $this->facilityValidation
      ->expects($this->once())
      ->method('assertFacilityIsAssignable')
      ->with(self::FACILITY_ID, self::ORGANIZATION_ID);
    $this->maintenanceSynchronizer->expects($this->never())->method('syncForStatusTransition');

    $equipmentId = 'e91d8400-e29b-41d4-a716-4466551c0550';
    $this->persistEquipment($equipmentId);
    $this->entityManager->flush();
    $this->entityManager->clear();

    $this->adapter->apply(self::ORGANIZATION_ID, '/api/equipment/' . $equipmentId, ['facility' => self::FACILITY_IRI]);
    $this->entityManager->flush();
    $this->entityManager->clear();

    $reloaded = $this->entityManager->find(EquipmentRecord::class, $equipmentId);
    self::assertInstanceOf(EquipmentRecord::class, $reloaded);
    self::assertSame(self::FACILITY_ID, $reloaded->facilityId);
    self::assertSame(2, $reloaded->revision);
  }

  #[Test]
  public function testApplyClearsFacility(): void
  {
    $this->facilityValidation->expects($this->never())->method('assertFacilityIsAssignable');
    $this->maintenanceSynchronizer->expects($this->never())->method('syncForStatusTransition');

    $equipmentId = 'e91d8400-e29b-41d4-a716-4466551c0560';
    $this->persistEquipment($equipmentId, facilityId: self::FACILITY_ID);
    $this->entityManager->flush();
    $this->entityManager->clear();

    $this->adapter->apply(self::ORGANIZATION_ID, '/api/equipment/' . $equipmentId, ['facility' => null]);
    $this->entityManager->flush();
    $this->entityManager->clear();

    $reloaded = $this->entityManager->find(EquipmentRecord::class, $equipmentId);
    self::assertInstanceOf(EquipmentRecord::class, $reloaded);
    self::assertNull($reloaded->facilityId);
    self::assertSame(2, $reloaded->revision);
  }

  #[Test]
  public function testApplyRejectsFacilityWhenValidationFails(): void
  {
    $this->facilityValidation
      ->expects($this->once())
      ->method('assertFacilityIsAssignable')
      ->willThrowException(new InvalidArgumentException('Facility is archived.'));
    $this->maintenanceSynchronizer->expects($this->never())->method('syncForStatusTransition');

    $equipmentId = 'e91d8400-e29b-41d4-a716-4466551c0570';
    $this->persistEquipment($equipmentId);
    $this->entityManager->flush();
    $this->entityManager->clear();

    $this->assertApplyThrows(
      self::ORGANIZATION_ID,
      '/api/equipment/' . $equipmentId,
      ['facility' => self::FACILITY_IRI],
      'Proposed equipment facility is invalid.',
    );
  }

  #[Test]
  #[AllowMockObjectsWithoutExpectations]
  public function testApplyRejectsInServiceEquipmentWithoutFacility(): void
  {
    $this->maintenanceSynchronizer->expects($this->never())->method('syncForStatusTransition');

    $equipmentId = 'e91d8400-e29b-41d4-a716-4466551c0580';
    $this->persistEquipment($equipmentId);
    $this->entityManager->flush();
    $this->entityManager->clear();

    $this->assertApplyThrows(
      self::ORGANIZATION_ID,
      '/api/equipment/' . $equipmentId,
      ['status' => 'operational'],
      'In-service equipment must be assigned to a facility.',
    );
  }

  #[Test]
  public function testApplyCommissionsEquipmentAndSyncsMaintenanceLog(): void
  {
    $equipmentId = 'e91d8400-e29b-41d4-a716-4466551c0590';

    $this->facilityValidation
      ->expects($this->once())
      ->method('assertFacilityIsAssignable')
      ->with(self::FACILITY_ID, self::ORGANIZATION_ID);
    $this->maintenanceSynchronizer
      ->expects($this->once())
      ->method('syncForStatusTransition')
      ->with($equipmentId, self::ORGANIZATION_ID, 'in_stock', 'operational');

    $this->persistEquipment($equipmentId);
    $this->entityManager->flush();
    $this->entityManager->clear();

    $this->adapter->apply(self::ORGANIZATION_ID, '/api/equipment/' . $equipmentId, [
      'status' => 'operational',
      'facility' => self::FACILITY_IRI,
    ]);
    $this->entityManager->flush();
    $this->entityManager->clear();

    $reloaded = $this->entityManager->find(EquipmentRecord::class, $equipmentId);
    self::assertInstanceOf(EquipmentRecord::class, $reloaded);
    self::assertSame('operational', $reloaded->status);
    self::assertSame(self::FACILITY_ID, $reloaded->facilityId);
    self::assertNotNull($reloaded->commissionedAt);
    self::assertSame(2, $reloaded->revision);
  }

  #[Test]
  #[AllowMockObjectsWithoutExpectations]
  public function testApplyRejectsIllegalStatusTransition(): void
  {
    $this->maintenanceSynchronizer->expects($this->never())->method('syncForStatusTransition');

    // in_stock -> under_maintenance is illegal (only operational/decommissioned are
    // legal). A facility is set so the in-service guard passes and we reach the
    // status-machine check.
    $equipmentId = 'e91d8400-e29b-41d4-a716-4466551c05a0';
    $this->persistEquipment($equipmentId, facilityId: self::FACILITY_ID);
    $this->entityManager->flush();
    $this->entityManager->clear();

    $this->assertApplyThrows(
      self::ORGANIZATION_ID,
      '/api/equipment/' . $equipmentId,
      ['status' => 'under_maintenance'],
      'Illegal equipment status transition from in_stock to under_maintenance.',
    );
  }

  #[Test]
  #[AllowMockObjectsWithoutExpectations]
  public function testPublishDraftsMaterializesDraftsAndSyncsMaintenance(): void
  {
    $inStock = 'e91d8400-e29b-41d4-a716-4466551c0610';
    $operational = 'e91d8400-e29b-41d4-a716-4466551c0611';
    $underMaintenance = 'e91d8400-e29b-41d4-a716-4466551c0612';
    $alreadyPublished = 'e91d8400-e29b-41d4-a716-4466551c0613';

    $this->maintenanceSynchronizer
      ->expects($this->once())
      ->method('syncForStatusTransition')
      ->with($underMaintenance, self::ORGANIZATION_ID, 'in_stock', 'under_maintenance');

    $this->persistEquipment($inStock, recordStatus: 'draft', interventionId: self::INTERVENTION_PUBLISH);
    $this->persistEquipment($operational, status: 'operational', recordStatus: 'draft', interventionId: self::INTERVENTION_PUBLISH, facilityId: self::FACILITY_ID);
    $this->persistEquipment($underMaintenance, status: 'under_maintenance', recordStatus: 'draft', interventionId: self::INTERVENTION_PUBLISH, facilityId: self::FACILITY_ID);
    $this->persistEquipment($alreadyPublished, recordStatus: 'published', interventionId: self::INTERVENTION_PUBLISH, revision: 5);
    $this->entityManager->flush();
    $this->entityManager->clear();

    $this->adapter->publishDrafts(self::INTERVENTION_PUBLISH);
    $this->entityManager->clear();

    $inStockRecord = $this->entityManager->find(EquipmentRecord::class, $inStock);
    self::assertInstanceOf(EquipmentRecord::class, $inStockRecord);
    self::assertSame('published', $inStockRecord->recordStatus);
    self::assertSame(2, $inStockRecord->revision);
    self::assertNull($inStockRecord->commissionedAt);

    $operationalRecord = $this->entityManager->find(EquipmentRecord::class, $operational);
    self::assertInstanceOf(EquipmentRecord::class, $operationalRecord);
    self::assertSame('published', $operationalRecord->recordStatus);
    self::assertNotNull($operationalRecord->commissionedAt);

    $underMaintenanceRecord = $this->entityManager->find(EquipmentRecord::class, $underMaintenance);
    self::assertInstanceOf(EquipmentRecord::class, $underMaintenanceRecord);
    self::assertSame('published', $underMaintenanceRecord->recordStatus);
    self::assertNotNull($underMaintenanceRecord->commissionedAt);

    // An already-published record for the same intervention is left untouched.
    $publishedRecord = $this->entityManager->find(EquipmentRecord::class, $alreadyPublished);
    self::assertInstanceOf(EquipmentRecord::class, $publishedRecord);
    self::assertSame(5, $publishedRecord->revision);
  }

  #[Test]
  #[AllowMockObjectsWithoutExpectations]
  public function testDiscardDraftsDeletesOnlyDrafts(): void
  {
    $draft = 'e91d8400-e29b-41d4-a716-4466551c0710';
    $published = 'e91d8400-e29b-41d4-a716-4466551c0711';
    $this->persistEquipment($draft, recordStatus: 'draft', interventionId: self::INTERVENTION_DISCARD);
    $this->persistEquipment($published, recordStatus: 'published', interventionId: self::INTERVENTION_DISCARD);
    $this->entityManager->flush();
    $this->entityManager->clear();

    $this->adapter->discardDrafts(self::INTERVENTION_DISCARD);
    $this->entityManager->clear();

    self::assertNull($this->entityManager->find(EquipmentRecord::class, $draft));
    self::assertInstanceOf(
      EquipmentRecord::class,
      $this->entityManager->find(EquipmentRecord::class, $published),
    );
  }

  /**
   * Asserts that apply() rejects the given patch with an InterventionConflictException
   * whose message contains the expected fragment, then rolls back any in-memory
   * mutation (apply() never flushes) so the shared fixture stays reusable.
   *
   * @param array<string, mixed> $patch the proposed change
   */
  private function assertApplyThrows(string $organizationId, string $resource, array $patch, string $messageFragment): void
  {
    try {
      $this->adapter->apply($organizationId, $resource, $patch);
      self::fail('Expected InterventionConflictException was not thrown.');
    } catch (InterventionConflictException $exception) {
      self::assertStringContainsString($messageFragment, $exception->getMessage());
    } finally {
      $this->entityManager->clear();
    }
  }

  private function createOrganization(): void
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $organization->name = 'Equipment Intervention Test';
    $organization->slug = 'equipment-intervention-test';
    $organization->ownerUserId = 'e91d8400-e29b-41d4-a716-4466551c9000';
    $organization->createdByUserId = 'e91d8400-e29b-41d4-a716-4466551c9000';
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $organization->updatedAt = $organization->createdAt;
    $this->entityManager->persist($organization);
  }

  private function persistEquipment(
    string $id,
    string $status = 'in_stock',
    string $recordStatus = 'published',
    ?string $interventionId = null,
    ?string $facilityId = null,
    ?string $clientId = null,
    ?string $serialNumber = null,
    ?string $locationLabel = null,
    int $revision = 1,
  ): void {
    $organization = $this->entityManager->getReference(OrganizationRecord::class, self::ORGANIZATION_ID);

    $equipment = new EquipmentRecord();
    $equipment->id = $id;
    $equipment->organization = $organization;
    $equipment->interventionId = $interventionId;
    $equipment->clientId = $clientId;
    $equipment->recordStatus = $recordStatus;
    $equipment->revision = $revision;
    $equipment->facilityId = $facilityId;
    $equipment->type = 'fire_extinguisher';
    $equipment->serialNumber = $serialNumber;
    $equipment->locationLabel = $locationLabel;
    $equipment->status = $status;
    $equipment->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $equipment->updatedAt = $equipment->createdAt;
    $this->entityManager->persist($equipment);
  }

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
}
