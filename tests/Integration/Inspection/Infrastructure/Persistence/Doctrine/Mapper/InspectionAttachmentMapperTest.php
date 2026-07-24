<?php

declare(strict_types=1);

namespace Tests\Integration\Inspection\Infrastructure\Persistence\Doctrine\Mapper;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Inspection\Domain\Model\Attachment\InspectionAttachment;
use Inspection\Domain\ValueObject\{InspectionAttachmentId, InspectionId, NonConformityId};
use Inspection\Infrastructure\Persistence\Doctrine\Mapper\InspectionAttachmentMapper;
use Inspection\Infrastructure\Persistence\Doctrine\Record\{InspectionAttachmentRecord, InspectionRecord, NonConformityRecord};
use LogicException;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test InspectionAttachmentMapperTest.
 *
 * Boots the kernel and uses the real `main` database so that `toDomain`
 * runs against genuinely persisted `InspectionAttachmentRecord` rows (with
 * their `inspection`/`nonConformity` relations loaded from the DB), covering
 * both the inspection-level document branch (`non_conformity_id` null) and the
 * non-conformity field-proof photo branch, plus the guard that rejects a record
 * with no inspection. `toRecord` is exercised as the reverse mapping.
 *
 * The mapper is a static-only utility (no constructor, not a registered
 * service), so its methods are invoked statically rather than fetched from the
 * container.
 *
 * @category Mapper Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InspectionAttachmentMapper::class)]
final class InspectionAttachmentMapperTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = '770e8400-e29b-41d4-a716-4466554a1001';

  private const string INSPECTION_ID = '770e8400-e29b-41d4-a716-4466554a1002';

  private const string NON_CONFORMITY_ID = '770e8400-e29b-41d4-a716-4466554a1003';

  private const string INSPECTION_LEVEL_ATTACHMENT_ID = '770e8400-e29b-41d4-a716-4466554a1004';

  private const string NON_CONFORMITY_ATTACHMENT_ID = '770e8400-e29b-41d4-a716-4466554a1005';

  private const string UPLOADED_AT = '2026-02-03T09:15:00+00:00';

  private EntityManagerInterface $entityManager;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();

    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $organization->name = 'Inspection Attachment Mapper Test';
    $organization->slug = 'inspection-attachment-mapper-test';
    $organization->ownerUserId = '770e8400-e29b-41d4-a716-4466554a9000';
    $organization->createdByUserId = '770e8400-e29b-41d4-a716-4466554a9000';
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $organization->updatedAt = $organization->createdAt;
    $this->entityManager->persist($organization);

    $inspection = new InspectionRecord();
    $inspection->id = self::INSPECTION_ID;
    $inspection->organization = $organization;
    $inspection->equipmentId = '770e8400-e29b-41d4-a716-4466554a8888';
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

    $uploadedAt = new DateTimeImmutable(self::UPLOADED_AT);

    $inspectionLevel = new InspectionAttachmentRecord();
    $inspectionLevel->id = self::INSPECTION_LEVEL_ATTACHMENT_ID;
    $inspectionLevel->inspection = $inspection;
    $inspectionLevel->fileName = 'report.pdf';
    $inspectionLevel->storagePath = 'inspection/' . self::INSPECTION_ID . '/attachments/' . self::INSPECTION_LEVEL_ATTACHMENT_ID . '_report.pdf';
    $inspectionLevel->mimeType = 'application/pdf';
    $inspectionLevel->size = 2048;
    $inspectionLevel->label = 'Signed report';
    $inspectionLevel->uploadedAt = $uploadedAt;
    $this->entityManager->persist($inspectionLevel);

    $nonConformityPhoto = new InspectionAttachmentRecord();
    $nonConformityPhoto->id = self::NON_CONFORMITY_ATTACHMENT_ID;
    $nonConformityPhoto->inspection = $inspection;
    $nonConformityPhoto->nonConformity = $nonConformity;
    $nonConformityPhoto->fileName = 'photo.jpg';
    $nonConformityPhoto->storagePath = 'inspection/' . self::INSPECTION_ID . '/attachments/' . self::NON_CONFORMITY_ATTACHMENT_ID . '_photo.jpg';
    $nonConformityPhoto->mimeType = 'image/jpeg';
    $nonConformityPhoto->size = 512;
    $nonConformityPhoto->uploadedAt = $uploadedAt;
    $this->entityManager->persist($nonConformityPhoto);

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
  public function testToDomainMapsAnInspectionLevelAttachmentWithoutNonConformity(): void
  {
    $record = $this->entityManager->find(InspectionAttachmentRecord::class, self::INSPECTION_LEVEL_ATTACHMENT_ID);
    self::assertInstanceOf(InspectionAttachmentRecord::class, $record);

    $attachment = InspectionAttachmentMapper::toDomain($record);

    self::assertSame(self::INSPECTION_LEVEL_ATTACHMENT_ID, (string) $attachment->id());
    self::assertSame(self::INSPECTION_ID, (string) $attachment->inspectionId());
    self::assertNull($attachment->nonConformityId());
    self::assertSame('report.pdf', $attachment->fileName());
    self::assertSame(
      'inspection/' . self::INSPECTION_ID . '/attachments/' . self::INSPECTION_LEVEL_ATTACHMENT_ID . '_report.pdf',
      $attachment->storagePath(),
    );
    self::assertSame('application/pdf', $attachment->mimeType());
    self::assertSame(2048, $attachment->size());
    self::assertSame('Signed report', $attachment->label());
    self::assertSame(self::UPLOADED_AT, $attachment->uploadedAt()->format(DateTimeImmutable::ATOM));
  }

  #[Test]
  public function testToDomainMapsANonConformityPhotoThroughTheDiscriminator(): void
  {
    $record = $this->entityManager->find(InspectionAttachmentRecord::class, self::NON_CONFORMITY_ATTACHMENT_ID);
    self::assertInstanceOf(InspectionAttachmentRecord::class, $record);

    $attachment = InspectionAttachmentMapper::toDomain($record);

    self::assertSame(self::NON_CONFORMITY_ATTACHMENT_ID, (string) $attachment->id());
    self::assertSame(self::INSPECTION_ID, (string) $attachment->inspectionId());
    self::assertNotNull($attachment->nonConformityId());
    self::assertSame(self::NON_CONFORMITY_ID, (string) $attachment->nonConformityId());
    self::assertNull($attachment->label());
    self::assertSame('image/jpeg', $attachment->mimeType());
    self::assertSame(512, $attachment->size());
  }

  #[Test]
  public function testToDomainRejectsARecordWithNoInspection(): void
  {
    $orphan = new InspectionAttachmentRecord();
    $orphan->id = self::INSPECTION_LEVEL_ATTACHMENT_ID;
    $orphan->fileName = 'orphan.pdf';
    $orphan->storagePath = 'inspection/orphan.pdf';
    $orphan->mimeType = 'application/pdf';
    $orphan->size = 1;
    $orphan->uploadedAt = new DateTimeImmutable(self::UPLOADED_AT);

    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('Attachment record must reference an inspection.');

    InspectionAttachmentMapper::toDomain($orphan);
  }

  #[Test]
  public function testToRecordMapsScalarFieldsAndLeavesRelationsUnset(): void
  {
    $attachment = InspectionAttachment::reconstitute(
      id: InspectionAttachmentId::fromString(self::INSPECTION_LEVEL_ATTACHMENT_ID),
      inspectionId: InspectionId::fromString(self::INSPECTION_ID),
      fileName: 'report.pdf',
      storagePath: 'inspection/' . self::INSPECTION_ID . '/attachments/report.pdf',
      mimeType: 'application/pdf',
      size: 4096,
      uploadedAt: new DateTimeImmutable(self::UPLOADED_AT),
      nonConformityId: NonConformityId::fromString(self::NON_CONFORMITY_ID),
      label: 'Signed report',
    );

    $record = InspectionAttachmentMapper::toRecord($attachment);

    self::assertSame(self::INSPECTION_LEVEL_ATTACHMENT_ID, $record->id);
    self::assertSame('report.pdf', $record->fileName);
    self::assertSame('inspection/' . self::INSPECTION_ID . '/attachments/report.pdf', $record->storagePath);
    self::assertSame('application/pdf', $record->mimeType);
    self::assertSame(4096, $record->size);
    self::assertSame('Signed report', $record->label);
    self::assertSame(self::UPLOADED_AT, $record->uploadedAt->format(DateTimeImmutable::ATOM));
    // The mapper intentionally leaves the FK relations for the repository to wire.
    self::assertNull($record->inspection);
    self::assertNull($record->nonConformity);
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
