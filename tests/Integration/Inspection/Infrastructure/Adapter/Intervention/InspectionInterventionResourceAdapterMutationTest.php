<?php

declare(strict_types=1);

namespace Tests\Integration\Inspection\Infrastructure\Adapter\Intervention;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Inspection\Infrastructure\Adapter\Intervention\InspectionInterventionResourceAdapter;
use Inspection\Infrastructure\Persistence\Doctrine\Record\{InspectionRecord, InspectionResponseRecord};
use Intervention\Domain\Exception\{InterventionConflictException, InterventionResourceNotFoundException};
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test InspectionInterventionResourceAdapter — mutation paths.
 *
 * Complements InspectionInterventionResourceAdapterTest by exercising the write
 * side of the adapter that the read-oriented sibling leaves uncovered: the
 * proposed-change validator (apply), the draft publication / discard bulk DQL,
 * and the assign() not-found guard.
 *
 * @category Adapter Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InspectionInterventionResourceAdapter::class)]
final class InspectionInterventionResourceAdapterMutationTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = 'f10e8400-e29b-41d4-a716-4466551a0001';

  private const string OTHER_ORGANIZATION_ID = 'f10e8400-e29b-41d4-a716-4466551a0002';

  private const string EQUIPMENT_ID = 'f10e8400-e29b-41d4-a716-4466551a00e1';

  private const string INTERVENTION_ID = 'f10e8400-e29b-41d4-a716-4466551a00a1';

  private const string OWNER_USER_ID = 'f10e8400-e29b-41d4-a716-4466551a9000';

  private const string OLD_TIMESTAMP = '2026-01-01T00:00:00+00:00';

  private EntityManagerInterface $entityManager;

  private InspectionInterventionResourceAdapter $adapter;

  protected function setUp(): void
  {
    self::bootKernel();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();

    /** @var InspectionInterventionResourceAdapter $adapter */
    $adapter = static::getContainer()->get(InspectionInterventionResourceAdapter::class);
    $this->adapter = $adapter;

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
  public function testApplyUpdatesFieldsBumpsRevisionAndTimestamp(): void
  {
    $inspectionId = 'f10e8400-e29b-41d4-a716-4466551a0b01';
    $this->persistInspection($inspectionId, status: 'draft', result: 'pass');

    $this->adapter->apply(self::ORGANIZATION_ID, '/api/inspections/' . $inspectionId, [
      'result' => 'fail',
      'status' => 'submitted',
      'notes' => 'Cracked casing observed.',
      'signature' => null,
    ]);
    $this->entityManager->flush();
    $this->entityManager->clear();

    $record = $this->entityManager->find(InspectionRecord::class, $inspectionId);
    self::assertInstanceOf(InspectionRecord::class, $record);
    self::assertSame('fail', $record->result);
    self::assertSame('submitted', $record->status);
    self::assertSame('Cracked casing observed.', $record->notes);
    self::assertNull($record->signature);
    self::assertSame(2, $record->revision);
    self::assertGreaterThan(new DateTimeImmutable(self::OLD_TIMESTAMP), $record->updatedAt);
  }

  #[Test]
  public function testApplyRejectsUnknownPatchFields(): void
  {
    $this->expectException(InterventionConflictException::class);

    $this->adapter->apply(
      self::ORGANIZATION_ID,
      '/api/inspections/f10e8400-e29b-41d4-a716-4466551a0b01',
      ['unsupported' => 'x'],
    );
  }

  #[Test]
  public function testApplyRejectsInvalidResourceIri(): void
  {
    $this->expectException(InterventionConflictException::class);

    $this->adapter->apply(self::ORGANIZATION_ID, '/api/inspections/', ['result' => 'pass']);
  }

  #[Test]
  public function testApplyRejectsNonPublishedRecord(): void
  {
    $inspectionId = 'f10e8400-e29b-41d4-a716-4466551a0b01';
    $this->persistInspection($inspectionId, recordStatus: 'draft');

    $this->expectException(InterventionConflictException::class);

    $this->adapter->apply(self::ORGANIZATION_ID, '/api/inspections/' . $inspectionId, ['result' => 'pass']);
  }

  #[Test]
  public function testApplyRejectsForeignOrganization(): void
  {
    $inspectionId = 'f10e8400-e29b-41d4-a716-4466551a0b01';
    $this->persistInspection($inspectionId);

    $this->expectException(InterventionConflictException::class);

    $this->adapter->apply(self::OTHER_ORGANIZATION_ID, '/api/inspections/' . $inspectionId, ['result' => 'pass']);
  }

  #[Test]
  public function testApplyRejectsMissingRecord(): void
  {
    $this->expectException(InterventionConflictException::class);

    $this->adapter->apply(
      self::ORGANIZATION_ID,
      '/api/inspections/f10e8400-e29b-41d4-a716-4466551a0bff',
      ['result' => 'pass'],
    );
  }

  #[Test]
  public function testApplyRejectsClosedInspection(): void
  {
    $inspectionId = 'f10e8400-e29b-41d4-a716-4466551a0b01';
    $this->persistInspection($inspectionId, status: 'closed');

    $this->expectException(InterventionConflictException::class);

    $this->adapter->apply(self::ORGANIZATION_ID, '/api/inspections/' . $inspectionId, ['notes' => 'x']);
  }

  #[Test]
  public function testApplyRejectsInvalidResultValue(): void
  {
    $inspectionId = 'f10e8400-e29b-41d4-a716-4466551a0b01';
    $this->persistInspection($inspectionId);

    $this->expectException(InterventionConflictException::class);

    $this->adapter->apply(self::ORGANIZATION_ID, '/api/inspections/' . $inspectionId, ['result' => 'bogus']);
  }

  #[Test]
  public function testApplyRejectsInvalidStatusValue(): void
  {
    $inspectionId = 'f10e8400-e29b-41d4-a716-4466551a0b01';
    $this->persistInspection($inspectionId);

    $this->expectException(InterventionConflictException::class);

    $this->adapter->apply(self::ORGANIZATION_ID, '/api/inspections/' . $inspectionId, ['status' => 'bogus']);
  }

  #[Test]
  public function testApplyRejectsIllegalStatusTransition(): void
  {
    $inspectionId = 'f10e8400-e29b-41d4-a716-4466551a0b01';
    $this->persistInspection($inspectionId, status: 'draft');

    $this->expectException(InterventionConflictException::class);

    // draft -> closed skips the mandatory submitted step and is rejected.
    $this->adapter->apply(self::ORGANIZATION_ID, '/api/inspections/' . $inspectionId, ['status' => 'closed']);
  }

  #[Test]
  public function testApplyRejectsNonStringNotes(): void
  {
    $inspectionId = 'f10e8400-e29b-41d4-a716-4466551a0b01';
    $this->persistInspection($inspectionId);

    $this->expectException(InterventionConflictException::class);

    $this->adapter->apply(self::ORGANIZATION_ID, '/api/inspections/' . $inspectionId, ['notes' => 123]);
  }

  #[Test]
  public function testAssignThrowsWhenResourceMissing(): void
  {
    $this->expectException(InterventionResourceNotFoundException::class);

    $this->adapter->assign('f10e8400-e29b-41d4-a716-4466551a0bff', self::INTERVENTION_ID, null);
  }

  #[Test]
  public function testPublishDraftsPromotesRecordsAndResponses(): void
  {
    $inspectionId = 'f10e8400-e29b-41d4-a716-4466551a0b01';
    $responseId = 'f10e8400-e29b-41d4-a716-4466551a0d01';
    $this->persistInspection($inspectionId, interventionId: self::INTERVENTION_ID, recordStatus: 'draft');
    $this->persistResponse($responseId, $inspectionId, self::INTERVENTION_ID, 'draft');

    $this->adapter->publishDrafts(self::INTERVENTION_ID);
    $this->entityManager->clear();

    $record = $this->entityManager->find(InspectionRecord::class, $inspectionId);
    self::assertInstanceOf(InspectionRecord::class, $record);
    self::assertSame('published', $record->recordStatus);
    self::assertSame(2, $record->revision);

    $response = $this->entityManager->find(InspectionResponseRecord::class, $responseId);
    self::assertInstanceOf(InspectionResponseRecord::class, $response);
    self::assertSame('published', $response->recordStatus);
    self::assertSame(2, $response->revision);
  }

  #[Test]
  public function testDiscardDraftsRemovesOnlyDraftRecords(): void
  {
    $draftInspectionId = 'f10e8400-e29b-41d4-a716-4466551a0b01';
    $publishedInspectionId = 'f10e8400-e29b-41d4-a716-4466551a0b02';
    $draftResponseId = 'f10e8400-e29b-41d4-a716-4466551a0d01';
    $publishedResponseId = 'f10e8400-e29b-41d4-a716-4466551a0d02';

    $this->persistInspection($draftInspectionId, interventionId: self::INTERVENTION_ID, recordStatus: 'draft');
    $this->persistInspection($publishedInspectionId, interventionId: self::INTERVENTION_ID, recordStatus: 'published');
    $this->persistResponse($draftResponseId, $draftInspectionId, self::INTERVENTION_ID, 'draft');
    $this->persistResponse($publishedResponseId, $publishedInspectionId, self::INTERVENTION_ID, 'published');

    $this->adapter->discardDrafts(self::INTERVENTION_ID);
    $this->entityManager->clear();

    self::assertNull($this->entityManager->find(InspectionRecord::class, $draftInspectionId));
    self::assertNull($this->entityManager->find(InspectionResponseRecord::class, $draftResponseId));

    self::assertInstanceOf(
      InspectionRecord::class,
      $this->entityManager->find(InspectionRecord::class, $publishedInspectionId),
    );
    self::assertInstanceOf(
      InspectionResponseRecord::class,
      $this->entityManager->find(InspectionResponseRecord::class, $publishedResponseId),
    );
  }

  private function persistInspection(
    string $id,
    ?string $interventionId = null,
    string $recordStatus = 'published',
    string $status = 'draft',
    string $result = 'pass',
  ): void {
    $organization = $this->entityManager->getReference(OrganizationRecord::class, self::ORGANIZATION_ID);

    $record = new InspectionRecord();
    $record->id = $id;
    $record->organization = $organization;
    $record->interventionId = $interventionId;
    $record->clientId = null;
    $record->recordStatus = $recordStatus;
    $record->revision = 1;
    $record->equipmentId = self::EQUIPMENT_ID;
    $record->inspectorType = 'user';
    $record->inspectorName = 'Jane Doe';
    $record->result = $result;
    $record->status = $status;
    $record->performedAt = new DateTimeImmutable(self::OLD_TIMESTAMP);
    $record->createdAt = new DateTimeImmutable(self::OLD_TIMESTAMP);
    $record->updatedAt = new DateTimeImmutable(self::OLD_TIMESTAMP);
    $this->entityManager->persist($record);
    $this->entityManager->flush();
    $this->entityManager->clear();
  }

  private function persistResponse(
    string $id,
    string $inspectionId,
    ?string $interventionId,
    string $recordStatus,
  ): void {
    $organization = $this->entityManager->getReference(OrganizationRecord::class, self::ORGANIZATION_ID);

    $record = new InspectionResponseRecord();
    $record->id = $id;
    $record->organization = $organization;
    $record->interventionId = $interventionId;
    $record->inspectionId = $inspectionId;
    $record->recordStatus = $recordStatus;
    $record->revision = 1;
    $record->itemKey = 'extinguisher.pressure';
    $record->value = ['ok' => true];
    $record->createdAt = new DateTimeImmutable(self::OLD_TIMESTAMP);
    $record->updatedAt = new DateTimeImmutable(self::OLD_TIMESTAMP);
    $this->entityManager->persist($record);
    $this->entityManager->flush();
    $this->entityManager->clear();
  }

  private function createOrganization(): void
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $organization->name = 'Intervention Resource Mutation Org';
    $organization->slug = 'intervention-resource-mutation-org';
    $organization->ownerUserId = self::OWNER_USER_ID;
    $organization->createdByUserId = self::OWNER_USER_ID;
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable(self::OLD_TIMESTAMP);
    $organization->updatedAt = $organization->createdAt;
    $this->entityManager->persist($organization);
  }

  private function cleanup(): void
  {
    $connection = $this->entityManager->getConnection();
    $connection->executeStatement(
      'DELETE FROM inspection_responses WHERE organization_id = :organizationId',
      ['organizationId' => self::ORGANIZATION_ID],
    );
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
}
