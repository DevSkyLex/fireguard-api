<?php

declare(strict_types=1);

namespace Tests\Integration\Intervention\Infrastructure\Adapter\Resource;

use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Inspection\Infrastructure\Persistence\Doctrine\Record\InspectionRecord;
use Intervention\Application\Contract\Resource\{InterventionAssignmentContext, InterventionValidationContext};
use Intervention\Application\Port\Outbound\{InterventionEquipmentDraftProviderPort, InterventionResourceOwnerPort};
use Intervention\Domain\Exception\InterventionResourceNotFoundException;
use Intervention\Domain\ValueObject\InterventionResourceType;
use Intervention\Infrastructure\Adapter\Resource\DoctrineInterventionResourceGatewayAdapter;
use Intervention\Infrastructure\Persistence\Doctrine\Record\{InterventionChangeRecord, InterventionRecord, InterventionWorkItemRecord};
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test DoctrineInterventionResourceGatewayAdapterTest.
 *
 * This adapter is the Intervention module's read/write gateway onto the
 * canonical resource tables: it locks interventions for mutation, counts work
 * items and proposed changes with hand-written DQL, and fans out to the
 * per-resource owner adapters. All of that only means anything against a real
 * database, so it is exercised here rather than through a mocked QueryBuilder.
 *
 * @category Adapter Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(DoctrineInterventionResourceGatewayAdapter::class)]
final class DoctrineInterventionResourceGatewayAdapterTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = '994e8400-e29b-41d4-a716-446655494001';

  private const string INTERVENTION_ID = '994e8400-e29b-41d4-a716-446655494101';

  private const string SUBMITTED_INTERVENTION_ID = '994e8400-e29b-41d4-a716-446655494102';

  private const string UNKNOWN_INTERVENTION_ID = '994e8400-e29b-41d4-a716-4466554941ff';

  private const string WORK_ITEM_REQUIRED_ID = '994e8400-e29b-41d4-a716-446655494201';

  private const string WORK_ITEM_DONE_ID = '994e8400-e29b-41d4-a716-446655494202';

  private const string WORK_ITEM_SKIPPED_ID = '994e8400-e29b-41d4-a716-446655494203';

  private const string CHANGE_ID = '994e8400-e29b-41d4-a716-446655494301';

  private const string EQUIPMENT_ID = '994e8400-e29b-41d4-a716-446655494401';

  private const string INSPECTION_ID = '994e8400-e29b-41d4-a716-446655494501';

  private const string CLIENT_ID = '994e8400-e29b-41d4-a716-446655494502';

  private const string OWNER_USER_ID = '994e8400-e29b-41d4-a716-446655499000';

  private EntityManagerInterface $entityManager;

  private DoctrineInterventionResourceGatewayAdapter $adapter;

  protected function setUp(): void
  {
    self::bootKernel();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();

    /** @var DoctrineInterventionResourceGatewayAdapter $adapter */
    $adapter = static::getContainer()->get(DoctrineInterventionResourceGatewayAdapter::class);
    $this->adapter = $adapter;

    $this->createOrganization();
    $this->createIntervention(self::INTERVENTION_ID, 'Draft Intervention', 9001, 'draft');
    $this->createIntervention(self::SUBMITTED_INTERVENTION_ID, 'Submitted Intervention', 9002, 'submitted');
    $this->entityManager->flush();
  }

  protected function tearDown(): void
  {
    $this->cleanup();
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testAssignmentContextProjectsTheInterventionAndReturnsNullWhenUnknown(): void
  {
    $context = $this->adapter->interventionAssignmentContext(self::INTERVENTION_ID);

    self::assertInstanceOf(InterventionAssignmentContext::class, $context);
    self::assertSame(self::INTERVENTION_ID, $context->interventionId);
    self::assertSame(self::ORGANIZATION_ID, $context->organizationId);
    self::assertSame('draft', $context->status);

    self::assertNull($this->adapter->interventionAssignmentContext(self::UNKNOWN_INTERVENTION_ID));
  }

  #[Test]
  public function testMutationContextLocksTheInterventionAndReturnsNullWhenUnknown(): void
  {
    // PESSIMISTIC_WRITE needs an ORM-level transaction, exactly as the
    // production call sites provide via wrapInTransaction, so the
    // SELECT ... FOR UPDATE is issued for real here.
    $this->entityManager->wrapInTransaction(function (): void {
      $context = $this->adapter->interventionMutationContext(self::INTERVENTION_ID);

      self::assertInstanceOf(InterventionAssignmentContext::class, $context);
      self::assertSame(self::ORGANIZATION_ID, $context->organizationId);
      self::assertSame('draft', $context->status);

      self::assertNull($this->adapter->interventionMutationContext(self::UNKNOWN_INTERVENTION_ID));
    });
  }

  #[Test]
  public function testValidationContextProjectsTheInterventionAndReturnsNullWhenUnknown(): void
  {
    $context = $this->adapter->validationContext(self::INTERVENTION_ID);

    self::assertInstanceOf(InterventionValidationContext::class, $context);
    self::assertSame('site_setup', $context->type);
    self::assertSame('draft', $context->status);

    self::assertNull($this->adapter->validationContext(self::UNKNOWN_INTERVENTION_ID));
  }

  #[Test]
  public function testResourceInInterventionScopeMatchesTheWorkItemTargetIri(): void
  {
    $this->createWorkItem(self::WORK_ITEM_REQUIRED_ID, 'planned', required: true, target: '/api/equipment/' . self::EQUIPMENT_ID);
    $this->entityManager->flush();
    $this->entityManager->clear();

    self::assertTrue($this->adapter->resourceInInterventionScope(
      InterventionResourceType::EQUIPMENT,
      self::EQUIPMENT_ID,
      self::INTERVENTION_ID,
    ));
    // The same identifier under a different resource type builds a different
    // target IRI and must not match.
    self::assertFalse($this->adapter->resourceInInterventionScope(
      InterventionResourceType::FACILITY,
      self::EQUIPMENT_ID,
      self::INTERVENTION_ID,
    ));
    self::assertFalse($this->adapter->resourceInInterventionScope(
      InterventionResourceType::INSPECTION,
      self::EQUIPMENT_ID,
      self::INTERVENTION_ID,
    ));
  }

  #[Test]
  public function testWorkItemSummaryAggregatesEveryBucket(): void
  {
    $this->createWorkItem(self::WORK_ITEM_REQUIRED_ID, 'planned', required: true);
    $this->createWorkItem(self::WORK_ITEM_DONE_ID, 'completed', required: true);
    $this->createWorkItem(self::WORK_ITEM_SKIPPED_ID, 'skipped', required: false, source: 'discovered');
    $this->entityManager->flush();
    $this->entityManager->clear();

    $summary = $this->adapter->workItemSummary(self::INTERVENTION_ID);

    self::assertSame(3, $summary->total);
    self::assertSame(1, $summary->requiredIncomplete);
    self::assertSame(1, $summary->skipped);
    self::assertSame(1, $summary->discovered);
    self::assertSame(2, $summary->completed);
  }

  #[Test]
  public function testResourceSummaryCountsEachOwnedResourceType(): void
  {
    $summary = $this->adapter->summary(self::INTERVENTION_ID);

    self::assertSame(0, $summary->facilities);
    self::assertSame(0, $summary->equipment);
    self::assertSame(0, $summary->inspections);
  }

  #[Test]
  public function testListMetricsAggregatesWorkItemsAndProposedChanges(): void
  {
    $this->createWorkItem(self::WORK_ITEM_REQUIRED_ID, 'planned', required: true);
    $this->createWorkItem(self::WORK_ITEM_DONE_ID, 'completed', required: true);
    $this->createChange(self::CHANGE_ID, 'proposed');
    $this->entityManager->flush();
    $this->entityManager->clear();

    $metrics = $this->adapter->listMetrics([self::INTERVENTION_ID, self::SUBMITTED_INTERVENTION_ID]);

    self::assertArrayHasKey(self::INTERVENTION_ID, $metrics);
    self::assertSame(2, $metrics[self::INTERVENTION_ID]->workItems);
    self::assertSame(1, $metrics[self::INTERVENTION_ID]->completedWorkItems);
    self::assertSame(1, $metrics[self::INTERVENTION_ID]->requiredIncomplete);
    self::assertSame(1, $metrics[self::INTERVENTION_ID]->proposedChanges);
    self::assertSame(0, $metrics[self::INTERVENTION_ID]->resourceBlockers);
    // An intervention with no rows still gets a zeroed metrics entry.
    self::assertSame(0, $metrics[self::SUBMITTED_INTERVENTION_ID]->workItems);
    self::assertSame(0, $metrics[self::SUBMITTED_INTERVENTION_ID]->proposedChanges);
  }

  #[Test]
  public function testListMetricsShortCircuitsOnAnEmptyIdList(): void
  {
    self::assertSame([], $this->adapter->listMetrics([]));
  }

  #[Test]
  public function testTouchDraftInterventionBumpsAMutableInterventionOnly(): void
  {
    $before = $this->revisionOf(self::INTERVENTION_ID);
    $submittedBefore = $this->revisionOf(self::SUBMITTED_INTERVENTION_ID);

    $this->adapter->touchDraftIntervention(self::INTERVENTION_ID);
    $this->entityManager->clear();
    self::assertSame($before + 1, $this->revisionOf(self::INTERVENTION_ID));

    // Submitted interventions are frozen, and a null id is a no-op.
    $this->adapter->touchDraftIntervention(self::SUBMITTED_INTERVENTION_ID);
    $this->adapter->touchDraftIntervention(null);
    $this->adapter->touchDraftIntervention(self::UNKNOWN_INTERVENTION_ID);
    $this->entityManager->clear();
    self::assertSame($submittedBefore, $this->revisionOf(self::SUBMITTED_INTERVENTION_ID));
  }

  #[Test]
  public function testEquipmentDraftsDelegatesToTheDraftProvider(): void
  {
    self::assertSame([], $this->adapter->equipmentDrafts(self::INTERVENTION_ID));
  }

  #[Test]
  public function testOwnerLookupFailsWhenNoOwnerSupportsTheResourceType(): void
  {
    /** @var InterventionEquipmentDraftProviderPort $draftProvider */
    $draftProvider = static::getContainer()->get(InterventionEquipmentDraftProviderPort::class);
    $adapter = new DoctrineInterventionResourceGatewayAdapter($this->entityManager, [], $draftProvider);

    $this->expectException(InterventionResourceNotFoundException::class);

    $adapter->resourceExists(InterventionResourceType::EQUIPMENT, self::EQUIPMENT_ID);
  }

  #[Test]
  public function testOwnershipAndClientIdLookupsDelegateToTheResourceOwner(): void
  {
    $this->createInspection();
    $this->entityManager->flush();
    $this->entityManager->clear();

    self::assertTrue($this->adapter->resourceExists(InterventionResourceType::INSPECTION, self::INSPECTION_ID));
    self::assertTrue($this->adapter->resourceBelongsToOrganization(
      InterventionResourceType::INSPECTION,
      self::INSPECTION_ID,
      self::ORGANIZATION_ID,
    ));
    self::assertFalse($this->adapter->resourceBelongsToOrganization(
      InterventionResourceType::INSPECTION,
      self::INSPECTION_ID,
      self::UNKNOWN_INTERVENTION_ID,
    ));
    self::assertTrue($this->adapter->clientIdExists(InterventionResourceType::INSPECTION, self::CLIENT_ID));
    self::assertFalse($this->adapter->clientIdExists(InterventionResourceType::INSPECTION, 'no-such-client-id'));
  }

  #[Test]
  public function testAssignAttachesTheResourceAndBumpsTheInterventionRevision(): void
  {
    $this->createInspection();
    $this->entityManager->flush();
    $this->entityManager->clear();

    $before = $this->revisionOf(self::INTERVENTION_ID);

    $assignment = $this->adapter->assign(
      InterventionResourceType::INSPECTION,
      self::INSPECTION_ID,
      self::INTERVENTION_ID,
      self::CLIENT_ID,
    );

    self::assertSame(self::INTERVENTION_ID, $assignment->interventionId);
    self::assertSame('draft', $assignment->recordStatus);
    self::assertSame($before + 1, $this->revisionOf(self::INTERVENTION_ID));

    // Detaching publishes the resource and leaves the intervention untouched.
    $afterAttach = $this->revisionOf(self::INTERVENTION_ID);
    $detached = $this->adapter->assign(InterventionResourceType::INSPECTION, self::INSPECTION_ID, null, null);

    self::assertNull($detached->interventionId);
    self::assertSame('published', $detached->recordStatus);
    self::assertSame($afterAttach, $this->revisionOf(self::INTERVENTION_ID));
  }

  #[Test]
  public function testListMetricsFoldsOwnerResourceCountsAndBlockers(): void
  {
    // A single stub owner claims every resource type, so its counts land in
    // all three resource fields and its blockers accumulate across them.
    $owner = $this->createStub(InterventionResourceOwnerPort::class);
    $owner->method('supportsResourceType')->willReturn(true);
    $owner->method('countsForInterventions')->willReturn([self::INTERVENTION_ID => 2, 'unrelated-id' => 9]);
    $owner->method('blockerCountsForInterventions')->willReturn([self::INTERVENTION_ID => 1, 'unrelated-id' => 9]);

    /** @var InterventionEquipmentDraftProviderPort $draftProvider */
    $draftProvider = static::getContainer()->get(InterventionEquipmentDraftProviderPort::class);
    $adapter = new DoctrineInterventionResourceGatewayAdapter($this->entityManager, [$owner], $draftProvider);

    $metrics = $adapter->listMetrics([self::INTERVENTION_ID]);

    self::assertSame(2, $metrics[self::INTERVENTION_ID]->facilities);
    self::assertSame(2, $metrics[self::INTERVENTION_ID]->equipment);
    self::assertSame(2, $metrics[self::INTERVENTION_ID]->inspections);
    self::assertSame(3, $metrics[self::INTERVENTION_ID]->resourceBlockers);
    // Counts keyed on an intervention outside the requested list are dropped.
    self::assertArrayNotHasKey('unrelated-id', $metrics);
  }

  private function createInspection(): void
  {
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, self::ORGANIZATION_ID);

    $record = new InspectionRecord();
    $record->id = self::INSPECTION_ID;
    $record->organization = $organization;
    $record->clientId = self::CLIENT_ID;
    $record->equipmentId = self::EQUIPMENT_ID;
    $record->inspectorType = 'external';
    $record->inspectorName = 'Inspector';
    $record->result = 'pass';
    $record->status = 'draft';
    $record->performedAt = new DateTimeImmutable('2026-03-01T10:00:00+00:00');
    $record->createdAt = $record->performedAt;
    $record->updatedAt = $record->performedAt;
    $this->entityManager->persist($record);
  }

  private function revisionOf(string $interventionId): int
  {
    $intervention = $this->entityManager->find(InterventionRecord::class, $interventionId);
    self::assertInstanceOf(InterventionRecord::class, $intervention);

    return $intervention->revision;
  }

  private function createWorkItem(
    string $id,
    string $status,
    bool $required,
    string $source = 'planned',
    ?string $target = null,
  ): void {
    /** @var InterventionRecord $intervention */
    $intervention = $this->entityManager->getReference(InterventionRecord::class, self::INTERVENTION_ID);

    $record = new InterventionWorkItemRecord();
    $record->id = $id;
    $record->intervention = $intervention;
    $record->action = 'Inspect the panel';
    $record->target = $target;
    $record->status = $status;
    $record->source = $source;
    $record->required = $required;
    $record->createdAt = new DateTimeImmutable('2026-03-01T10:00:00+00:00');
    $record->updatedAt = $record->createdAt;
    $this->entityManager->persist($record);
  }

  private function createChange(string $id, string $status): void
  {
    /** @var InterventionRecord $intervention */
    $intervention = $this->entityManager->getReference(InterventionRecord::class, self::INTERVENTION_ID);

    $record = new InterventionChangeRecord();
    $record->id = $id;
    $record->intervention = $intervention;
    $record->resource = 'equipment';
    $record->patch = ['name' => 'Renamed'];
    $record->status = $status;
    $record->createdAt = new DateTimeImmutable('2026-03-01T10:00:00+00:00');
    $record->updatedAt = $record->createdAt;
    $this->entityManager->persist($record);
  }

  private function createIntervention(string $id, string $name, int $number, string $status): void
  {
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, self::ORGANIZATION_ID);

    $record = new InterventionRecord();
    $record->id = $id;
    $record->organization = $organization;
    $record->type = 'site_setup';
    $record->name = $name;
    $record->number = $number;
    $record->status = $status;
    $record->priority = 'normal';
    $record->createdAt = new DateTimeImmutable('2026-03-01T10:00:00+00:00');
    $record->updatedAt = $record->createdAt;
    $this->entityManager->persist($record);
  }

  private function createOrganization(): void
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $organization->name = 'Intervention Resource Gateway Test';
    $organization->slug = 'intervention-resource-gateway-test';
    $organization->ownerUserId = self::OWNER_USER_ID;
    $organization->createdByUserId = self::OWNER_USER_ID;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $organization->updatedAt = $organization->createdAt;
    $this->entityManager->persist($organization);
  }

  private function cleanup(): void
  {
    $interventionIds = [self::INTERVENTION_ID, self::SUBMITTED_INTERVENTION_ID];
    $connection = $this->entityManager->getConnection();
    $connection->executeStatement(
      'DELETE FROM inspections WHERE id = :inspectionId',
      ['inspectionId' => self::INSPECTION_ID],
    );
    $connection->executeStatement(
      'DELETE FROM intervention_changes WHERE intervention_id IN (:interventionIds)',
      ['interventionIds' => $interventionIds],
      ['interventionIds' => ArrayParameterType::STRING],
    );
    $connection->executeStatement(
      'DELETE FROM intervention_work_items WHERE intervention_id IN (:interventionIds)',
      ['interventionIds' => $interventionIds],
      ['interventionIds' => ArrayParameterType::STRING],
    );
    $connection->executeStatement(
      'DELETE FROM interventions WHERE id IN (:interventionIds)',
      ['interventionIds' => $interventionIds],
      ['interventionIds' => ArrayParameterType::STRING],
    );
    $connection->executeStatement(
      'DELETE FROM organizations WHERE id = :organizationId',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $this->entityManager->clear();
  }
}
