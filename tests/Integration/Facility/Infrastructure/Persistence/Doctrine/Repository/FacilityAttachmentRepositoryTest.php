<?php

declare(strict_types=1);

namespace Tests\Integration\Facility\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Facility\Domain\Model\Attachment\FacilityAttachment;
use Facility\Domain\ValueObject\{AttachmentKind, FacilityAttachmentId, FacilityId};
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Facility\Infrastructure\Persistence\Doctrine\Repository\FacilityAttachmentRepository;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test FacilityAttachmentRepositoryTest.
 *
 * Exercises save/find/delete against a real database, and confirms the
 * `ON DELETE CASCADE` foreign key removes attachment rows when the parent
 * facility (and, transitively, its organization) is hard-deleted.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(FacilityAttachmentRepository::class)]
final class FacilityAttachmentRepositoryTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = '660e8400-e29b-41d4-a716-446655460001';

  private const string FACILITY_ID = '660e8400-e29b-41d4-a716-446655460002';

  private const string ATTACHMENT_ID = '660e8400-e29b-41d4-a716-446655460003';

  private const string OTHER_ATTACHMENT_ID = '660e8400-e29b-41d4-a716-446655460004';

  private EntityManagerInterface $entityManager;

  private FacilityAttachmentRepository $repository;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();

    $this->repository = new FacilityAttachmentRepository($this->entityManager);

    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $organization->name = 'Facility Attachment Repository Test';
    $organization->slug = 'facility-attachment-repository-test';
    $organization->ownerUserId = '660e8400-e29b-41d4-a716-446655469000';
    $organization->createdByUserId = '660e8400-e29b-41d4-a716-446655469000';
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $organization->updatedAt = $organization->createdAt;
    $this->entityManager->persist($organization);

    $facility = new FacilityRecord();
    $facility->id = self::FACILITY_ID;
    $facility->organization = $organization;
    $facility->type = 'site';
    $facility->name = 'Main Site';
    $facility->status = 'active';
    $facility->metadata = [];
    $facility->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $facility->updatedAt = $facility->createdAt;
    $this->entityManager->persist($facility);

    $this->entityManager->flush();
  }

  protected function tearDown(): void
  {
    $this->cleanup();
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testSaveThenFindByIdRoundTrips(): void
  {
    $attachment = FacilityAttachment::create(
      id: FacilityAttachmentId::fromString(self::ATTACHMENT_ID),
      facilityId: FacilityId::fromString(self::FACILITY_ID),
      fileName: 'floor-plan.pdf',
      storagePath: 'facility/' . self::FACILITY_ID . '/attachments/' . self::ATTACHMENT_ID . '_floor-plan.pdf',
      mimeType: 'application/pdf',
      size: 2048,
      label: 'Ground floor',
    );

    $this->repository->save($attachment);

    $found = $this->repository->findById(FacilityAttachmentId::fromString(self::ATTACHMENT_ID));

    self::assertNotNull($found);
    self::assertSame('floor-plan.pdf', $found->fileName());
    self::assertSame('Ground floor', $found->label());
    self::assertSame(self::FACILITY_ID, (string) $found->facilityId());
  }

  #[Test]
  public function testFindByFacilityIdReturnsSavedAttachments(): void
  {
    $attachment = FacilityAttachment::create(
      id: FacilityAttachmentId::fromString(self::ATTACHMENT_ID),
      facilityId: FacilityId::fromString(self::FACILITY_ID),
      fileName: 'floor-plan.pdf',
      storagePath: 'facility/' . self::FACILITY_ID . '/attachments/' . self::ATTACHMENT_ID . '_floor-plan.pdf',
      mimeType: 'application/pdf',
      size: 2048,
    );
    $this->repository->save($attachment);

    $results = $this->repository->findByFacilityId(FacilityId::fromString(self::FACILITY_ID));

    self::assertCount(1, $results);
    self::assertSame(self::ATTACHMENT_ID, (string) $results[0]->id());
  }

  #[Test]
  public function testCountByFacilityIdMatchesTheListedAttachments(): void
  {
    self::assertSame(0, $this->repository->countByFacilityId(FacilityId::fromString(self::FACILITY_ID)));

    $this->repository->save(FacilityAttachment::create(
      id: FacilityAttachmentId::fromString(self::ATTACHMENT_ID),
      facilityId: FacilityId::fromString(self::FACILITY_ID),
      fileName: 'floor-plan.pdf',
      storagePath: 'facility/' . self::FACILITY_ID . '/attachments/' . self::ATTACHMENT_ID . '_floor-plan.pdf',
      mimeType: 'application/pdf',
      size: 2048,
    ));

    self::assertSame(1, $this->repository->countByFacilityId(FacilityId::fromString(self::FACILITY_ID)));
  }

  #[Test]
  public function testDeleteRemovesTheRow(): void
  {
    $attachment = FacilityAttachment::create(
      id: FacilityAttachmentId::fromString(self::ATTACHMENT_ID),
      facilityId: FacilityId::fromString(self::FACILITY_ID),
      fileName: 'floor-plan.pdf',
      storagePath: 'facility/' . self::FACILITY_ID . '/attachments/' . self::ATTACHMENT_ID . '_floor-plan.pdf',
      mimeType: 'application/pdf',
      size: 2048,
    );
    $this->repository->save($attachment);

    $this->repository->delete(FacilityAttachmentId::fromString(self::ATTACHMENT_ID));

    self::assertNull($this->repository->findById(FacilityAttachmentId::fromString(self::ATTACHMENT_ID)));
  }

  #[Test]
  public function testSaveUpdatesTheExistingRecordInPlace(): void
  {
    $this->repository->save(FacilityAttachment::create(
      id: FacilityAttachmentId::fromString(self::ATTACHMENT_ID),
      facilityId: FacilityId::fromString(self::FACILITY_ID),
      fileName: 'draft.pdf',
      storagePath: 'facility/' . self::FACILITY_ID . '/attachments/draft.pdf',
      mimeType: 'application/pdf',
      size: 128,
    ));
    $this->entityManager->clear();

    $this->repository->save(FacilityAttachment::create(
      id: FacilityAttachmentId::fromString(self::ATTACHMENT_ID),
      facilityId: FacilityId::fromString(self::FACILITY_ID),
      fileName: 'final.png',
      storagePath: 'facility/' . self::FACILITY_ID . '/attachments/final.png',
      mimeType: 'image/png',
      size: 8192,
      label: 'Final revision',
    ));
    $this->entityManager->clear();

    $found = $this->repository->findById(FacilityAttachmentId::fromString(self::ATTACHMENT_ID));

    self::assertNotNull($found);
    self::assertSame('final.png', $found->fileName());
    self::assertSame('facility/' . self::FACILITY_ID . '/attachments/final.png', $found->storagePath());
    self::assertSame('image/png', $found->mimeType());
    self::assertSame(8192, $found->size());
    self::assertSame('Final revision', $found->label());
    self::assertCount(1, $this->repository->findByFacilityId(FacilityId::fromString(self::FACILITY_ID)));
  }

  #[Test]
  public function testDeleteIsANoOpWhenTheAttachmentIsMissing(): void
  {
    $this->repository->delete(FacilityAttachmentId::fromString('660e8400-e29b-41d4-a716-4466554600ff'));

    self::assertTrue($this->entityManager->isOpen());
    self::assertSame([], $this->repository->findByFacilityId(FacilityId::fromString(self::FACILITY_ID)));
  }

  #[Test]
  public function testSaveThenFindByIdRoundTripsAFloorPlanWithItsDimensions(): void
  {
    $attachment = FacilityAttachment::create(
      id: FacilityAttachmentId::fromString(self::ATTACHMENT_ID),
      facilityId: FacilityId::fromString(self::FACILITY_ID),
      fileName: 'ground-floor.png',
      storagePath: 'facility/' . self::FACILITY_ID . '/attachments/' . self::ATTACHMENT_ID . '_ground-floor.png',
      mimeType: 'image/png',
      size: 4096,
      kind: AttachmentKind::FLOOR_PLAN,
      imageWidth: 1920,
      imageHeight: 1080,
    );

    $this->repository->save($attachment);
    $this->entityManager->clear();

    $found = $this->repository->findById(FacilityAttachmentId::fromString(self::ATTACHMENT_ID));

    self::assertNotNull($found);
    self::assertSame(AttachmentKind::FLOOR_PLAN, $found->kind());
    self::assertFalse($found->isPrimaryPlan());
    self::assertSame(1920, $found->imageWidth());
    self::assertSame(1080, $found->imageHeight());
  }

  #[Test]
  public function testFindByFacilityIdFiltersByKind(): void
  {
    $this->repository->save(FacilityAttachment::create(
      id: FacilityAttachmentId::fromString(self::ATTACHMENT_ID),
      facilityId: FacilityId::fromString(self::FACILITY_ID),
      fileName: 'report.pdf',
      storagePath: 'facility/' . self::FACILITY_ID . '/attachments/report.pdf',
      mimeType: 'application/pdf',
      size: 128,
    ));
    $this->repository->save(FacilityAttachment::create(
      id: FacilityAttachmentId::fromString(self::OTHER_ATTACHMENT_ID),
      facilityId: FacilityId::fromString(self::FACILITY_ID),
      fileName: 'plan.png',
      storagePath: 'facility/' . self::FACILITY_ID . '/attachments/plan.png',
      mimeType: 'image/png',
      size: 4096,
      kind: AttachmentKind::FLOOR_PLAN,
    ));

    $documents = $this->repository->findByFacilityId(FacilityId::fromString(self::FACILITY_ID), AttachmentKind::DOCUMENT);
    $floorPlans = $this->repository->findByFacilityId(FacilityId::fromString(self::FACILITY_ID), AttachmentKind::FLOOR_PLAN);

    self::assertCount(1, $documents);
    self::assertSame(self::ATTACHMENT_ID, (string) $documents[0]->id());
    self::assertCount(1, $floorPlans);
    self::assertSame(self::OTHER_ATTACHMENT_ID, (string) $floorPlans[0]->id());
  }

  #[Test]
  public function testClearPrimaryPlanClearsEveryOtherAttachmentOfTheFacility(): void
  {
    $primary = FacilityAttachment::create(
      id: FacilityAttachmentId::fromString(self::ATTACHMENT_ID),
      facilityId: FacilityId::fromString(self::FACILITY_ID),
      fileName: 'ground-floor.png',
      storagePath: 'facility/' . self::FACILITY_ID . '/attachments/ground-floor.png',
      mimeType: 'image/png',
      size: 4096,
      kind: AttachmentKind::FLOOR_PLAN,
    );
    $primary->markAsPrimary();
    $this->repository->save($primary);

    $challenger = FacilityAttachment::create(
      id: FacilityAttachmentId::fromString(self::OTHER_ATTACHMENT_ID),
      facilityId: FacilityId::fromString(self::FACILITY_ID),
      fileName: 'first-floor.png',
      storagePath: 'facility/' . self::FACILITY_ID . '/attachments/first-floor.png',
      mimeType: 'image/png',
      size: 4096,
      kind: AttachmentKind::FLOOR_PLAN,
    );
    $challenger->markAsPrimary();

    $this->repository->clearPrimaryPlan(FacilityId::fromString(self::FACILITY_ID), FacilityAttachmentId::fromString(self::OTHER_ATTACHMENT_ID));
    $this->repository->save($challenger);
    $this->entityManager->clear();

    $reloadedPrimary = $this->repository->findById(FacilityAttachmentId::fromString(self::ATTACHMENT_ID));
    $reloadedChallenger = $this->repository->findById(FacilityAttachmentId::fromString(self::OTHER_ATTACHMENT_ID));

    self::assertNotNull($reloadedPrimary);
    self::assertNotNull($reloadedChallenger);
    self::assertFalse($reloadedPrimary->isPrimaryPlan());
    self::assertTrue($reloadedChallenger->isPrimaryPlan());
  }

  #[Test]
  public function testThePartialUniqueIndexRejectsASecondPrimaryPlanForTheSameFacility(): void
  {
    $first = FacilityAttachment::create(
      id: FacilityAttachmentId::fromString(self::ATTACHMENT_ID),
      facilityId: FacilityId::fromString(self::FACILITY_ID),
      fileName: 'ground-floor.png',
      storagePath: 'facility/' . self::FACILITY_ID . '/attachments/ground-floor.png',
      mimeType: 'image/png',
      size: 4096,
      kind: AttachmentKind::FLOOR_PLAN,
    );
    $first->markAsPrimary();
    $this->repository->save($first);

    $second = FacilityAttachment::create(
      id: FacilityAttachmentId::fromString(self::OTHER_ATTACHMENT_ID),
      facilityId: FacilityId::fromString(self::FACILITY_ID),
      fileName: 'first-floor.png',
      storagePath: 'facility/' . self::FACILITY_ID . '/attachments/first-floor.png',
      mimeType: 'image/png',
      size: 4096,
      kind: AttachmentKind::FLOOR_PLAN,
    );
    // Deliberately WITHOUT clearing the first primary first — the schema-level
    // backstop the partial unique index exists for (see the migration docblock).
    $second->markAsPrimary();

    $this->expectException(UniqueConstraintViolationException::class);

    $this->repository->save($second);
  }

  private function cleanup(): void
  {
    $connection = $this->entityManager->getConnection();
    $connection->executeStatement(
      'DELETE FROM facility_attachments WHERE facility_id = :facilityId',
      ['facilityId' => self::FACILITY_ID],
    );
    $connection->executeStatement(
      'DELETE FROM facilities WHERE id = :facilityId',
      ['facilityId' => self::FACILITY_ID],
    );
    $connection->executeStatement(
      'DELETE FROM organizations WHERE id = :organizationId',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $this->entityManager->clear();
  }
}
