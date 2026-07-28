<?php

declare(strict_types=1);

namespace Tests\Integration\Inspection\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Inspection\Domain\Model\Attachment\InspectionAttachment;
use Inspection\Domain\ValueObject\{InspectionAttachmentId, InspectionId, NonConformityId};
use Inspection\Infrastructure\Persistence\Doctrine\Record\{InspectionRecord, NonConformityRecord};
use Inspection\Infrastructure\Persistence\Doctrine\Repository\InspectionAttachmentRepository;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test InspectionAttachmentRepositoryTest.
 *
 * Exercises save/find/delete against a real database, including the
 * non_conformity_id discriminator that separates inspection-level
 * documents from non-conformity field-proof photos in the single
 * `inspection_attachments` table (see `src/Inspection/MODULE.md`).
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InspectionAttachmentRepository::class)]
final class InspectionAttachmentRepositoryTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = '660e8400-e29b-41d4-a716-446655461001';

  private const string INSPECTION_ID = '660e8400-e29b-41d4-a716-446655461002';

  private const string NON_CONFORMITY_ID = '660e8400-e29b-41d4-a716-446655461003';

  private const string INSPECTION_LEVEL_ATTACHMENT_ID = '660e8400-e29b-41d4-a716-446655461004';

  private const string NON_CONFORMITY_ATTACHMENT_ID = '660e8400-e29b-41d4-a716-446655461005';

  private EntityManagerInterface $entityManager;

  private InspectionAttachmentRepository $repository;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();

    $this->repository = new InspectionAttachmentRepository($this->entityManager);

    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $organization->name = 'Inspection Attachment Repository Test';
    $organization->slug = 'inspection-attachment-repository-test';
    $organization->ownerUserId = '660e8400-e29b-41d4-a716-446655469000';
    $organization->createdByUserId = '660e8400-e29b-41d4-a716-446655469000';
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $organization->updatedAt = $organization->createdAt;
    $this->entityManager->persist($organization);

    $inspection = new InspectionRecord();
    $inspection->id = self::INSPECTION_ID;
    $inspection->organization = $organization;
    $inspection->equipmentId = '660e8400-e29b-41d4-a716-446655468888';
    $inspection->inspectorType = 'user';
    $inspection->inspectorName = 'Jane Doe';
    $inspection->result = 'pass';
    $inspection->status = 'draft';
    $inspection->performedAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $inspection->createdAt = $inspection->performedAt;
    $inspection->updatedAt = $inspection->performedAt;
    $this->entityManager->persist($inspection);

    $nonConformity = new NonConformityRecord();
    $nonConformity->id = self::NON_CONFORMITY_ID;
    $nonConformity->inspection = $inspection;
    $nonConformity->description = 'Fire extinguisher pressure too low';
    $nonConformity->severity = 'high';
    $nonConformity->status = 'open';
    $nonConformity->createdAt = $inspection->performedAt;
    $nonConformity->updatedAt = $inspection->performedAt;
    $this->entityManager->persist($nonConformity);

    $this->entityManager->flush();
  }

  protected function tearDown(): void
  {
    $this->cleanup();
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testSaveThenFindByIdRoundTripsAnInspectionLevelAttachment(): void
  {
    $attachment = InspectionAttachment::create(
      id: InspectionAttachmentId::fromString(self::INSPECTION_LEVEL_ATTACHMENT_ID),
      inspectionId: InspectionId::fromString(self::INSPECTION_ID),
      fileName: 'report.pdf',
      storagePath: 'inspection/' . self::INSPECTION_ID . '/attachments/' . self::INSPECTION_LEVEL_ATTACHMENT_ID . '_report.pdf',
      mimeType: 'application/pdf',
      size: 2048,
    );

    $this->repository->save($attachment);

    $found = $this->repository->findById(InspectionAttachmentId::fromString(self::INSPECTION_LEVEL_ATTACHMENT_ID));

    self::assertNotNull($found);
    self::assertNull($found->nonConformityId());
    self::assertSame('report.pdf', $found->fileName());
  }

  #[Test]
  public function testSaveThenFindByIdRoundTripsANonConformityPhoto(): void
  {
    $attachment = InspectionAttachment::create(
      id: InspectionAttachmentId::fromString(self::NON_CONFORMITY_ATTACHMENT_ID),
      inspectionId: InspectionId::fromString(self::INSPECTION_ID),
      fileName: 'photo.jpg',
      storagePath: 'inspection/' . self::INSPECTION_ID . '/attachments/' . self::NON_CONFORMITY_ATTACHMENT_ID . '_photo.jpg',
      mimeType: 'image/jpeg',
      size: 512,
      nonConformityId: NonConformityId::fromString(self::NON_CONFORMITY_ID),
    );

    $this->repository->save($attachment);

    $found = $this->repository->findById(InspectionAttachmentId::fromString(self::NON_CONFORMITY_ATTACHMENT_ID));

    self::assertNotNull($found);
    self::assertNotNull($found->nonConformityId());
    self::assertSame(self::NON_CONFORMITY_ID, (string) $found->nonConformityId());
  }

  #[Test]
  public function testFindByInspectionIdExcludesNonConformityAttachments(): void
  {
    $this->repository->save(InspectionAttachment::create(
      id: InspectionAttachmentId::fromString(self::INSPECTION_LEVEL_ATTACHMENT_ID),
      inspectionId: InspectionId::fromString(self::INSPECTION_ID),
      fileName: 'report.pdf',
      storagePath: 'inspection/' . self::INSPECTION_ID . '/attachments/' . self::INSPECTION_LEVEL_ATTACHMENT_ID . '_report.pdf',
      mimeType: 'application/pdf',
      size: 2048,
    ));
    $this->repository->save(InspectionAttachment::create(
      id: InspectionAttachmentId::fromString(self::NON_CONFORMITY_ATTACHMENT_ID),
      inspectionId: InspectionId::fromString(self::INSPECTION_ID),
      fileName: 'photo.jpg',
      storagePath: 'inspection/' . self::INSPECTION_ID . '/attachments/' . self::NON_CONFORMITY_ATTACHMENT_ID . '_photo.jpg',
      mimeType: 'image/jpeg',
      size: 512,
      nonConformityId: NonConformityId::fromString(self::NON_CONFORMITY_ID),
    ));

    $inspectionLevel = $this->repository->findByInspectionId(InspectionId::fromString(self::INSPECTION_ID));
    $nonConformityLevel = $this->repository->findByNonConformityId(NonConformityId::fromString(self::NON_CONFORMITY_ID));

    self::assertCount(1, $inspectionLevel);
    self::assertSame(self::INSPECTION_LEVEL_ATTACHMENT_ID, (string) $inspectionLevel[0]->id());
    self::assertCount(1, $nonConformityLevel);
    self::assertSame(self::NON_CONFORMITY_ATTACHMENT_ID, (string) $nonConformityLevel[0]->id());
  }

  #[Test]
  public function testDeleteRemovesTheRow(): void
  {
    $this->repository->save(InspectionAttachment::create(
      id: InspectionAttachmentId::fromString(self::INSPECTION_LEVEL_ATTACHMENT_ID),
      inspectionId: InspectionId::fromString(self::INSPECTION_ID),
      fileName: 'report.pdf',
      storagePath: 'inspection/' . self::INSPECTION_ID . '/attachments/' . self::INSPECTION_LEVEL_ATTACHMENT_ID . '_report.pdf',
      mimeType: 'application/pdf',
      size: 2048,
    ));

    $this->repository->delete(InspectionAttachmentId::fromString(self::INSPECTION_LEVEL_ATTACHMENT_ID));

    self::assertNull($this->repository->findById(InspectionAttachmentId::fromString(self::INSPECTION_LEVEL_ATTACHMENT_ID)));
  }

  #[Test]
  public function testDeleteIsANoOpForAnUnknownAttachment(): void
  {
    // Deleting something already gone must not raise: the caller has no way to
    // distinguish "never existed" from "removed by a concurrent request".
    $this->repository->delete(InspectionAttachmentId::fromString('99999999-9999-4999-8999-999999999999'));

    self::assertNull($this->repository->findById(
      InspectionAttachmentId::fromString('99999999-9999-4999-8999-999999999999'),
    ));
  }

  #[Test]
  public function testSaveUpdatesTheExistingRecordInPlaceAndCanPromoteItToANonConformityPhoto(): void
  {
    $this->repository->save(InspectionAttachment::create(
      id: InspectionAttachmentId::fromString(self::INSPECTION_LEVEL_ATTACHMENT_ID),
      inspectionId: InspectionId::fromString(self::INSPECTION_ID),
      fileName: 'draft.pdf',
      storagePath: 'inspection/' . self::INSPECTION_ID . '/attachments/draft.pdf',
      mimeType: 'application/pdf',
      size: 128,
    ));
    $this->entityManager->clear();

    $this->repository->save(InspectionAttachment::create(
      id: InspectionAttachmentId::fromString(self::INSPECTION_LEVEL_ATTACHMENT_ID),
      inspectionId: InspectionId::fromString(self::INSPECTION_ID),
      fileName: 'proof.jpg',
      storagePath: 'inspection/' . self::INSPECTION_ID . '/attachments/proof.jpg',
      mimeType: 'image/jpeg',
      size: 6144,
      label: 'Field proof',
      nonConformityId: NonConformityId::fromString(self::NON_CONFORMITY_ID),
    ));
    $this->entityManager->clear();

    $found = $this->repository->findById(InspectionAttachmentId::fromString(self::INSPECTION_LEVEL_ATTACHMENT_ID));

    self::assertNotNull($found);
    self::assertSame('proof.jpg', $found->fileName());
    self::assertSame('inspection/' . self::INSPECTION_ID . '/attachments/proof.jpg', $found->storagePath());
    self::assertSame('image/jpeg', $found->mimeType());
    self::assertSame(6144, $found->size());
    self::assertSame('Field proof', $found->label());
    self::assertSame(self::NON_CONFORMITY_ID, (string) $found->nonConformityId());
    self::assertCount(1, $this->repository->findByNonConformityId(NonConformityId::fromString(self::NON_CONFORMITY_ID)));
  }

  private function cleanup(): void
  {
    $connection = $this->entityManager->getConnection();
    $connection->executeStatement(
      'DELETE FROM inspection_attachments WHERE inspection_id = :inspectionId',
      ['inspectionId' => self::INSPECTION_ID],
    );
    $connection->executeStatement(
      'DELETE FROM non_conformities WHERE inspection_id = :inspectionId',
      ['inspectionId' => self::INSPECTION_ID],
    );
    $connection->executeStatement(
      'DELETE FROM inspections WHERE id = :inspectionId',
      ['inspectionId' => self::INSPECTION_ID],
    );
    $connection->executeStatement(
      'DELETE FROM organizations WHERE id = :organizationId',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $this->entityManager->clear();
  }
}
